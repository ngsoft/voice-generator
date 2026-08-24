package client

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

// ConfigFileName est le nom cherché à côté de l'exécutable.
const ConfigFileName = "speak.json"

// EnvConfig désigne un fichier de configuration explicite.
const EnvConfig = "VOICE_CONFIG"

// ErrConfigInvalid signale un fichier de configuration illisible ou incohérent.
var ErrConfigInvalid = errors.New("client: configuration invalide")

// Config est la configuration JSON posée à côté de l'exécutable. Elle surcharge
// l'environnement : tout champ présent l'emporte sur la variable correspondante,
// qu'elle vienne du shell ou d'un fichier .env.
//
// Les champs absents du JSON sont laissés tels quels, d'où les pointeurs : une
// chaîne vide ou un false explicite reste une valeur, pas une absence.
type Config struct {
	// Comment et Schema sont acceptés pour documenter le fichier ; ils n'ont
	// aucun effet, JSON n'ayant pas de commentaires.
	Comment *string `json:"$comment,omitempty"`
	Schema  *string `json:"$schema,omitempty"`

	Env         *string  `json:"env,omitempty"`
	PHPBinary   *string  `json:"phpBinary,omitempty"`
	ConsolePath *string  `json:"consolePath,omitempty"`
	WorkingDir  *string  `json:"workingDir,omitempty"`
	PHPArgs     []string `json:"phpArgs,omitempty"`
	Lang        *string  `json:"lang,omitempty"`
	Voice       *string  `json:"voice,omitempty"`
	Timeout     *string  `json:"timeout,omitempty"`
	Verbosity   *string  `json:"verbosity,omitempty"`
	NoANSI      *bool    `json:"noAnsi,omitempty"`
	Interactive *bool    `json:"interactive,omitempty"`
}

// DefaultConfigPaths renvoie les emplacements fouillés par LoadConfig :
// VOICE_CONFIG s'il est défini, puis speak.json à côté de l'exécutable, puis
// dans le répertoire courant.
func DefaultConfigPaths() []string {
	var paths []string

	if path := os.Getenv(EnvConfig); path != "" {
		paths = append(paths, path)
	}

	if exe, err := os.Executable(); err == nil {
		paths = append(paths, filepath.Join(filepath.Dir(exe), ConfigFileName))
	}

	if cwd, err := os.Getwd(); err == nil {
		paths = append(paths, filepath.Join(cwd, ConfigFileName))
	}
	return paths
}

// LoadConfig lit le premier fichier de configuration existant et renvoie son
// contenu ainsi que son chemin. Sans argument, DefaultConfigPaths est utilisé.
//
// Une absence de fichier n'est pas une erreur : (nil, "", nil) est renvoyé. En
// revanche un fichier présent mais invalide — JSON malformé, clé inconnue,
// durée ou verbosité incorrecte — remonte une erreur ErrConfigInvalid.
func LoadConfig(paths ...string) (*Config, string, error) {
	if len(paths) == 0 {
		paths = DefaultConfigPaths()
	}

	for _, path := range paths {
		if path == "" {
			continue
		}

		info, err := os.Stat(path)

		if err != nil || info.IsDir() {
			continue
		}

		cfg, err := readConfig(path)

		if err != nil {
			return nil, path, err
		}
		return cfg, path, nil
	}
	return nil, "", nil
}

// readConfig décode et valide un fichier de configuration.
func readConfig(path string) (*Config, error) {
	file, err := os.Open(path)

	if err != nil {
		return nil, fmt.Errorf("%w: %s: %w", ErrConfigInvalid, path, err)
	}
	defer file.Close()

	decoder := json.NewDecoder(file)
	// Une clé inconnue est plus souvent une faute de frappe qu'une extension.
	decoder.DisallowUnknownFields()

	cfg := &Config{}

	if err := decoder.Decode(cfg); err != nil {
		return nil, fmt.Errorf("%w: %s: %w", ErrConfigInvalid, path, err)
	}

	if err := cfg.Validate(); err != nil {
		return nil, fmt.Errorf("%w: %s: %w", ErrConfigInvalid, path, err)
	}
	return cfg, nil
}

// Validate contrôle les champs dont le format est contraint.
func (c *Config) Validate() error {
	if c == nil {
		return nil
	}

	if c.Timeout != nil && strings.TrimSpace(*c.Timeout) != "" {
		if _, err := time.ParseDuration(*c.Timeout); err != nil {
			return fmt.Errorf("timeout %q : durée invalide (ex. 30s)", *c.Timeout)
		}
	}

	if c.Verbosity != nil {
		if _, err := ParseVerbosity(*c.Verbosity); err != nil {
			return err
		}
	}
	return nil
}

// Values renvoie les variables d'environnement équivalentes à la configuration.
func (c *Config) Values() map[string]string {
	values := map[string]string{}

	if c == nil {
		return values
	}

	fields := map[string]*string{
		EnvName:        c.Env,
		EnvPHPBinary:   c.PHPBinary,
		EnvConsolePath: c.ConsolePath,
		EnvWorkingDir:  c.WorkingDir,
		EnvLang:        c.Lang,
		EnvVoice:       c.Voice,
		EnvTimeout:     c.Timeout,
		EnvVerbosity:   c.Verbosity,
	}

	for name, value := range fields {
		if value != nil {
			values[name] = *value
		}
	}

	if c.PHPArgs != nil {
		values[EnvPHPArgs] = joinArgs(c.PHPArgs)
	}

	if c.NoANSI != nil {
		values[EnvNoANSI] = strconv.FormatBool(*c.NoANSI)
	}

	if c.Interactive != nil {
		values[EnvInteractive] = strconv.FormatBool(*c.Interactive)
	}
	return values
}

// Apply écrase l'environnement du processus avec les valeurs de la
// configuration. Le sous-processus php en hérite.
func (c *Config) Apply() error {
	for name, value := range c.Values() {
		if err := os.Setenv(name, value); err != nil {
			return fmt.Errorf("%w: %s=%q : %w", ErrConfigInvalid, name, value, err)
		}
	}
	return nil
}

// ApplyEnvName ne pose que le nom d'environnement, afin que la configuration
// puisse décider quels fichiers .env seront chargés ensuite.
func (c *Config) ApplyEnvName() error {
	if c == nil || c.Env == nil {
		return nil
	}

	if err := os.Setenv(EnvName, *c.Env); err != nil {
		return fmt.Errorf("%w: %s=%q : %w", ErrConfigInvalid, EnvName, *c.Env, err)
	}
	return nil
}

// joinArgs recompose une ligne d'arguments lisible par splitArgs.
func joinArgs(args []string) string {
	quoted := make([]string, 0, len(args))

	for _, arg := range args {
		if arg == "" || strings.ContainsAny(arg, " \t") {
			if strings.Contains(arg, `"`) {
				arg = "'" + arg + "'"
			} else {
				arg = `"` + arg + `"`
			}
		}
		quoted = append(quoted, arg)
	}
	return strings.Join(quoted, " ")
}
