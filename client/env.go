package client

import (
	"bufio"
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
	"unicode"
)

// Variables d'environnement lues par le client.
const (
	EnvDir         = "VOICE_ENV_DIR"
	EnvName        = "VOICE_ENV"
	EnvAppEnv      = "APP_ENV"
	EnvWorkingDir  = "VOICE_WORKING_DIR"
	EnvPHPArgs     = "VOICE_PHP_ARGS"
	EnvLang        = "VOICE_LANG"
	EnvVoice       = "VOICE_VOICE"
	EnvTimeout     = "VOICE_TIMEOUT"
	EnvVerbosity   = "VOICE_VERBOSITY"
	EnvNoANSI      = "VOICE_NO_ANSI"
	EnvInteractive = "VOICE_INTERACTIVE"
)

// EnvFileNames renvoie les fichiers à charger, du moins au plus prioritaire,
// selon la même convention que le projet PHP.
func EnvFileNames(name string) []string {
	names := []string{".env", ".env.local"}

	if name = strings.TrimSpace(name); name != "" {
		names = append(names, ".env."+name, ".env."+name+".local")
	}
	return names
}

// DefaultEnvDirs renvoie les répertoires fouillés par LoadEnv : VOICE_ENV_DIR
// s'il est défini, puis le répertoire courant, puis celui de l'exécutable.
func DefaultEnvDirs() []string {
	var dirs []string

	if dir := os.Getenv(EnvDir); dir != "" {
		dirs = append(dirs, dir)
	}

	if cwd, err := os.Getwd(); err == nil {
		dirs = append(dirs, cwd)
	}

	if exe, err := os.Executable(); err == nil {
		dirs = append(dirs, filepath.Dir(exe))
	}
	return dirs
}

// LoadEnv charge les fichiers .env du premier répertoire qui en contient et
// renvoie la liste des fichiers retenus.
//
// Sans argument, DefaultEnvDirs est utilisé. Les variables déjà présentes dans
// l'environnement réel ne sont jamais écrasées, comme côté PHP.
func LoadEnv(dirs ...string) ([]string, error) {
	if len(dirs) == 0 {
		dirs = DefaultEnvDirs()
	}

	for _, dir := range dirs {
		if dir == "" {
			continue
		}

		files, values, err := loadDir(dir)

		if err != nil {
			return nil, err
		}

		if len(files) == 0 {
			continue
		}

		for key, value := range values {
			if _, defined := os.LookupEnv(key); defined {
				continue
			}

			if err := os.Setenv(key, value); err != nil {
				return files, fmt.Errorf("client: %s=%q : %w", key, value, err)
			}
		}
		return files, nil
	}
	return nil, nil
}

// loadDir agrège les fichiers .env d'un répertoire. Le nom d'environnement est
// résolu après .env et .env.local, afin de savoir quels fichiers spécifiques
// charger ensuite.
func loadDir(dir string) ([]string, map[string]string, error) {
	var (
		files  []string
		values = map[string]string{}
	)

	read := func(names []string) error {
		for _, name := range names {
			path := filepath.Join(dir, name)
			info, err := os.Stat(path)

			if err != nil || info.IsDir() {
				continue
			}

			if err := parseEnvFile(path, values); err != nil {
				return err
			}
			files = append(files, path)
		}
		return nil
	}

	if err := read(EnvFileNames("")); err != nil {
		return nil, nil, err
	}

	if name := envName(values); name != "" {
		if err := read([]string{".env." + name, ".env." + name + ".local"}); err != nil {
			return nil, nil, err
		}
	}
	return files, values, nil
}

// envName détermine le suffixe des fichiers spécifiques à un environnement.
//
// VOICE_ENV est privilégié sur APP_ENV : les valeurs de ces fichiers sont
// héritées par le sous-processus php, où APP_ENV a sa propre signification.
func envName(values map[string]string) string {
	candidates := []string{
		os.Getenv(EnvName), values[EnvName],
		os.Getenv(EnvAppEnv), values[EnvAppEnv],
	}

	for _, candidate := range candidates {
		if candidate = strings.TrimSpace(candidate); candidate != "" {
			return candidate
		}
	}
	return ""
}

// parseEnvFile fusionne un fichier .env dans values, les lignes ultérieures
// écrasant les précédentes.
func parseEnvFile(path string, values map[string]string) error {
	file, err := os.Open(path)

	if err != nil {
		return fmt.Errorf("client: ouverture de %s : %w", path, err)
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)

	for line := 1; scanner.Scan(); line++ {
		key, value, ok, err := parseEnvLine(scanner.Text(), values)

		if err != nil {
			return fmt.Errorf("client: %s:%d : %w", path, line, err)
		}

		if ok {
			values[key] = value
		}
	}

	if err := scanner.Err(); err != nil {
		return fmt.Errorf("client: lecture de %s : %w", path, err)
	}
	return nil
}

// parseEnvLine décode une ligne « CLE=valeur ». Le troisième retour est faux
// pour les lignes vides et les commentaires.
func parseEnvLine(raw string, values map[string]string) (string, string, bool, error) {
	line := strings.TrimSpace(raw)

	if line == "" || strings.HasPrefix(line, "#") {
		return "", "", false, nil
	}

	line = strings.TrimPrefix(line, "export ")

	key, value, found := strings.Cut(line, "=")

	if !found {
		return "", "", false, fmt.Errorf("ligne sans « = » : %q", raw)
	}

	key = strings.TrimSpace(key)

	if !isValidEnvKey(key) {
		return "", "", false, fmt.Errorf("nom de variable invalide : %q", key)
	}

	value = strings.TrimSpace(value)

	switch {
	case strings.HasPrefix(value, "'") && strings.HasSuffix(value, "'") && len(value) >= 2:
		// Guillemets simples : valeur littérale, sans interpolation.
		return key, value[1 : len(value)-1], true, nil
	case strings.HasPrefix(value, `"`) && strings.HasSuffix(value, `"`) && len(value) >= 2:
		value = value[1 : len(value)-1]
	default:
		// Valeur nue : un commentaire de fin de ligne est retiré.
		if idx := strings.Index(value, " #"); idx >= 0 {
			value = strings.TrimSpace(value[:idx])
		}
	}
	return key, expandEnvValue(value, values), true, nil
}

// isValidEnvKey valide un nom de variable POSIX.
func isValidEnvKey(key string) bool {
	if key == "" {
		return false
	}

	for i, r := range key {
		switch {
		case r == '_':
		case unicode.IsLetter(r):
		case unicode.IsDigit(r) && i > 0:
		default:
			return false
		}
	}
	return true
}

// expandEnvValue remplace ${VAR} et $VAR par les valeurs déjà chargées, à
// défaut par l'environnement réel.
func expandEnvValue(value string, values map[string]string) string {
	return os.Expand(value, func(name string) string {
		if found, ok := values[name]; ok {
			return found
		}
		return os.Getenv(name)
	})
}

// OptionsFromEnv complète base avec les variables d'environnement : un champ
// déjà renseigné dans base l'emporte, l'environnement ne sert que de défaut.
func OptionsFromEnv(base Options) (Options, error) {
	opts := base

	if opts.PHPBinary == "" {
		opts.PHPBinary = os.Getenv(EnvPHPBinary)
	}

	if opts.ConsolePath == "" {
		opts.ConsolePath = os.Getenv(EnvConsolePath)
	}

	if opts.WorkingDir == "" {
		opts.WorkingDir = os.Getenv(EnvWorkingDir)
	}

	if len(opts.PHPArgs) == 0 {
		opts.PHPArgs = splitArgs(os.Getenv(EnvPHPArgs))
	}

	if opts.Timeout == 0 {
		if raw := strings.TrimSpace(os.Getenv(EnvTimeout)); raw != "" {
			timeout, err := time.ParseDuration(raw)

			if err != nil {
				return base, fmt.Errorf("client: %s=%q : durée invalide (ex. 30s)", EnvTimeout, raw)
			}
			opts.Timeout = timeout
		}
	}

	if opts.Verbosity == VerbosityNormal {
		if raw := strings.TrimSpace(os.Getenv(EnvVerbosity)); raw != "" {
			verbosity, err := ParseVerbosity(raw)

			if err != nil {
				return base, err
			}
			opts.Verbosity = verbosity
		}
	}

	if !opts.NoANSI {
		opts.NoANSI = envBool(EnvNoANSI)
	}

	if !opts.Interactive {
		opts.Interactive = envBool(EnvInteractive)
	}
	return opts, nil
}

// UtteranceFromEnv complète les champs vides de base depuis l'environnement.
func UtteranceFromEnv(base Utterance) Utterance {
	u := base

	if u.Lang == "" {
		u.Lang = strings.TrimSpace(os.Getenv(EnvLang))
	}

	if u.Voice == "" {
		u.Voice = strings.TrimSpace(os.Getenv(EnvVoice))
	}
	return u
}

// ParseVerbosity décode « quiet », « normal », « v »/« -v », « vv », « vvv »
// ou un niveau numérique de 0 à 3.
func ParseVerbosity(raw string) (Verbosity, error) {
	switch strings.ToLower(strings.TrimSpace(strings.TrimLeft(raw, "-"))) {
	case "", "normal", "0":
		return VerbosityNormal, nil
	case "quiet", "q":
		return VerbosityQuiet, nil
	case "verbose", "v", "1":
		return VerbosityVerbose, nil
	case "very-verbose", "vv", "2":
		return VerbosityVeryVerbose, nil
	case "debug", "vvv", "3":
		return VerbosityDebug, nil
	default:
		return VerbosityNormal, fmt.Errorf("client: verbosité inconnue : %q (quiet, normal, v, vv, vvv)", raw)
	}
}

// envBool interprète une variable booléenne façon dotenv.
func envBool(name string) bool {
	switch strings.ToLower(strings.TrimSpace(os.Getenv(name))) {
	case "yes", "on":
		return true
	case "no", "off":
		return false
	default:
		value, err := strconv.ParseBool(strings.TrimSpace(os.Getenv(name)))
		return err == nil && value
	}
}

// splitArgs découpe une ligne d'arguments en respectant les guillemets.
func splitArgs(raw string) []string {
	var (
		args    []string
		current strings.Builder
		quote   rune
		started bool
	)

	flush := func() {
		if started {
			args = append(args, current.String())
			current.Reset()
			started = false
		}
	}

	for _, r := range raw {
		switch {
		case quote != 0:
			if r == quote {
				quote = 0
			} else {
				current.WriteRune(r)
			}
		case r == '\'' || r == '"':
			quote, started = r, true
		case unicode.IsSpace(r):
			flush()
		default:
			current.WriteRune(r)
			started = true
		}
	}
	flush()
	return args
}
