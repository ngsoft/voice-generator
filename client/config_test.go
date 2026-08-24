package client

import (
	"errors"
	"os"
	"path/filepath"
	"slices"
	"testing"
)

// writeConfig écrit un speak.json dans un répertoire temporaire et renvoie son chemin.
func writeConfig(t *testing.T, content string) string {
	t.Helper()

	path := filepath.Join(t.TempDir(), ConfigFileName)

	if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
		t.Fatal(err)
	}
	return path
}

func TestLoadConfig(t *testing.T) {
	path := writeConfig(t, `{
	  "$comment": "documentation ignorée",
	  "env": "prod",
	  "phpBinary": "C:/php/php.exe",
	  "phpArgs": ["-d", "memory_limit=512M"],
	  "lang": "fr-FR",
	  "timeout": "45s",
	  "verbosity": "quiet",
	  "noAnsi": true,
	  "interactive": false
	}`)

	cfg, found, err := LoadConfig(path)

	if err != nil {
		t.Fatal(err)
	}

	if found != path {
		t.Errorf("chemin = %q, attendu %q", found, path)
	}

	if cfg.Lang == nil || *cfg.Lang != "fr-FR" {
		t.Errorf("lang = %v, attendu fr-FR", cfg.Lang)
	}

	if !slices.Equal(cfg.PHPArgs, []string{"-d", "memory_limit=512M"}) {
		t.Errorf("phpArgs = %v", cfg.PHPArgs)
	}

	if cfg.Interactive == nil || *cfg.Interactive {
		t.Errorf("interactive = %v, attendu false explicite", cfg.Interactive)
	}
}

func TestLoadConfigMissingIsNotAnError(t *testing.T) {
	cfg, found, err := LoadConfig(filepath.Join(t.TempDir(), ConfigFileName))

	if err != nil {
		t.Fatalf("un fichier absent ne doit pas être une erreur : %v", err)
	}

	if cfg != nil || found != "" {
		t.Errorf("config = %v, chemin = %q, attendu vide", cfg, found)
	}
}

func TestLoadConfigUsesFirstExistingPath(t *testing.T) {
	missing := filepath.Join(t.TempDir(), ConfigFileName)
	path := writeConfig(t, `{"voice": "en-GB-RyanNeural"}`)

	cfg, found, err := LoadConfig(missing, path)

	if err != nil {
		t.Fatal(err)
	}

	if found != path || cfg.Voice == nil || *cfg.Voice != "en-GB-RyanNeural" {
		t.Errorf("config = %v depuis %q, attendu le second chemin", cfg, found)
	}
}

func TestLoadConfigErrors(t *testing.T) {
	cases := map[string]string{
		"JSON malformé":      `{"lang": }`,
		"clé inconnue":       `{"langue": "fr-FR"}`,
		"durée invalide":     `{"timeout": "trente secondes"}`,
		"verbosité invalide": `{"verbosity": "bavard"}`,
		"type de champ faux": `{"noAnsi": "oui"}`,
	}

	for name, content := range cases {
		if _, _, err := LoadConfig(writeConfig(t, content)); !errors.Is(err, ErrConfigInvalid) {
			t.Errorf("%s : erreur = %v, attendu ErrConfigInvalid", name, err)
		}
	}
}

func TestConfigApplyOverridesEnvironment(t *testing.T) {
	clearEnv(t)
	t.Setenv(EnvLang, "es-ES")
	t.Setenv(EnvVoice, "es-ES-ElviraNeural")

	path := writeConfig(t, `{"lang": "fr-FR", "phpArgs": ["-d", "include_path=a b"], "noAnsi": true}`)
	cfg, _, err := LoadConfig(path)

	if err != nil {
		t.Fatal(err)
	}

	if err := cfg.Apply(); err != nil {
		t.Fatal(err)
	}

	if got := os.Getenv(EnvLang); got != "fr-FR" {
		t.Errorf("%s = %q, la configuration doit écraser l'environnement", EnvLang, got)
	}

	if got := os.Getenv(EnvVoice); got != "es-ES-ElviraNeural" {
		t.Errorf("%s = %q, un champ absent doit être laissé tel quel", EnvVoice, got)
	}

	opts, err := OptionsFromEnv(Options{})

	if err != nil {
		t.Fatal(err)
	}

	if want := []string{"-d", "include_path=a b"}; !slices.Equal(opts.PHPArgs, want) {
		t.Errorf("PHPArgs = %v, attendu %v", opts.PHPArgs, want)
	}

	if !opts.NoANSI {
		t.Error("NoANSI = false, attendu true")
	}
}

func TestConfigApplyEnvNameOnly(t *testing.T) {
	clearEnv(t)

	path := writeConfig(t, `{"env": "prod", "lang": "fr-FR"}`)
	cfg, _, err := LoadConfig(path)

	if err != nil {
		t.Fatal(err)
	}

	if err := cfg.ApplyEnvName(); err != nil {
		t.Fatal(err)
	}

	if got := os.Getenv(EnvName); got != "prod" {
		t.Errorf("%s = %q, attendu prod", EnvName, got)
	}

	if got := os.Getenv(EnvLang); got != "" {
		t.Errorf("%s = %q, ApplyEnvName ne doit poser que le nom d'environnement", EnvLang, got)
	}
}

func TestConfigNilIsSafe(t *testing.T) {
	var cfg *Config

	if err := cfg.Apply(); err != nil {
		t.Errorf("Apply sur nil : %v", err)
	}

	if err := cfg.ApplyEnvName(); err != nil {
		t.Errorf("ApplyEnvName sur nil : %v", err)
	}

	if len(cfg.Values()) != 0 {
		t.Errorf("Values sur nil = %v, attendu vide", cfg.Values())
	}
}

// TestConfigWinsOverEnvFiles reproduit l'ordre de la CLI : .env chargés d'abord,
// configuration appliquée ensuite.
func TestConfigWinsOverEnvFiles(t *testing.T) {
	clearEnv(t)

	dir := writeEnvFiles(t, map[string]string{
		".env":       "VOICE_LANG=en-US\nVOICE_VOICE=en-US-AvaMultilingualNeural\n",
		".env.local": "VOICE_LANG=de-DE\n",
	})

	cfg, _, err := LoadConfig(writeConfig(t, `{"lang": "fr-FR"}`))

	if err != nil {
		t.Fatal(err)
	}

	if _, err := LoadEnv(dir); err != nil {
		t.Fatal(err)
	}

	if err := cfg.Apply(); err != nil {
		t.Fatal(err)
	}

	u := UtteranceFromEnv(Utterance{Text: "Bonjour"})

	if u.Lang != "fr-FR" {
		t.Errorf("Lang = %q, la configuration doit primer sur .env.local", u.Lang)
	}

	if u.Voice != "en-US-AvaMultilingualNeural" {
		t.Errorf("Voice = %q, attendu la valeur du .env", u.Voice)
	}
}

func TestJoinArgsRoundTrip(t *testing.T) {
	cases := [][]string{
		{"-d", "memory_limit=512M"},
		{"-d", "include_path=a b"},
		{"-d", `auto_prepend_file=c:\a b"c`},
		{""},
	}

	for _, args := range cases {
		if got := splitArgs(joinArgs(args)); !slices.Equal(got, args) {
			t.Errorf("splitArgs(joinArgs(%v)) = %v", args, got)
		}
	}
}

func TestDefaultConfigPathsHonoursEnv(t *testing.T) {
	clearEnv(t)
	t.Setenv(EnvConfig, "D:/quelque/part/speak.json")

	paths := DefaultConfigPaths()

	if len(paths) == 0 || paths[0] != "D:/quelque/part/speak.json" {
		t.Errorf("DefaultConfigPaths = %v, %s doit venir en premier", paths, EnvConfig)
	}
}
