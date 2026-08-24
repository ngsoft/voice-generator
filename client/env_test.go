package client

import (
	"os"
	"path/filepath"
	"slices"
	"testing"
	"time"
)

// writeEnvFiles écrit des fichiers .env dans un répertoire temporaire.
func writeEnvFiles(t *testing.T, files map[string]string) string {
	t.Helper()

	dir := t.TempDir()

	for name, content := range files {
		if err := os.WriteFile(filepath.Join(dir, name), []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	return dir
}

// clearEnv neutralise les variables du client pour un test isolé.
func clearEnv(t *testing.T) {
	t.Helper()

	for _, name := range []string{
		EnvDir, EnvName, EnvAppEnv, EnvConfig, EnvPHPBinary, EnvConsolePath, EnvWorkingDir, EnvPHPArgs,
		EnvLang, EnvVoice, EnvTimeout, EnvVerbosity, EnvNoANSI, EnvInteractive,
	} {
		t.Setenv(name, "")
		os.Unsetenv(name)
	}
}

func TestLoadEnvPrecedence(t *testing.T) {
	clearEnv(t)

	dir := writeEnvFiles(t, map[string]string{
		".env":           "VOICE_ENV=dev\nVOICE_LANG=en-US\nVOICE_VOICE=en-US-AvaMultilingualNeural\n",
		".env.local":     "VOICE_LANG=fr-FR\n",
		".env.dev":       "VOICE_VERBOSITY=quiet\n",
		".env.dev.local": "VOICE_VOICE=fr-FR-DeniseNeural\n",
	})

	files, err := LoadEnv(dir)

	if err != nil {
		t.Fatal(err)
	}

	if len(files) != 4 {
		t.Errorf("fichiers chargés = %v, attendu 4", files)
	}

	for name, want := range map[string]string{
		EnvLang:      "fr-FR",              // .env.local écrase .env
		EnvVoice:     "fr-FR-DeniseNeural", // .env.dev.local écrase .env
		EnvVerbosity: "quiet",              // .env.dev, sélectionné via VOICE_ENV
	} {
		if got := os.Getenv(name); got != want {
			t.Errorf("%s = %q, attendu %q", name, got, want)
		}
	}
}

func TestLoadEnvKeepsRealEnvironment(t *testing.T) {
	clearEnv(t)
	t.Setenv(EnvLang, "es-ES")

	dir := writeEnvFiles(t, map[string]string{".env": "VOICE_LANG=en-US\n"})

	if _, err := LoadEnv(dir); err != nil {
		t.Fatal(err)
	}

	if got := os.Getenv(EnvLang); got != "es-ES" {
		t.Errorf("%s = %q, l'environnement réel doit primer", EnvLang, got)
	}
}

func TestLoadEnvUsesFirstDirectoryWithFiles(t *testing.T) {
	clearEnv(t)

	empty := t.TempDir()
	dir := writeEnvFiles(t, map[string]string{".env": "VOICE_VOICE=en-GB-RyanNeural\n"})

	files, err := LoadEnv(empty, dir)

	if err != nil {
		t.Fatal(err)
	}

	if len(files) != 1 || filepath.Dir(files[0]) != dir {
		t.Errorf("fichiers chargés = %v, attendu le .env de %s", files, dir)
	}
}

func TestLoadEnvNoFiles(t *testing.T) {
	clearEnv(t)

	files, err := LoadEnv(t.TempDir())

	if err != nil {
		t.Fatal(err)
	}

	if files != nil {
		t.Errorf("fichiers chargés = %v, attendu aucun", files)
	}
}

func TestLoadEnvFallsBackToAppEnv(t *testing.T) {
	clearEnv(t)
	t.Setenv(EnvAppEnv, "prod")

	dir := writeEnvFiles(t, map[string]string{
		".env":      "VOICE_LANG=en-US\n",
		".env.prod": "VOICE_LANG=fr-FR\n",
	})

	if _, err := LoadEnv(dir); err != nil {
		t.Fatal(err)
	}

	if got := os.Getenv(EnvLang); got != "fr-FR" {
		t.Errorf("%s = %q, attendu fr-FR (.env.prod via APP_ENV)", EnvLang, got)
	}
}

func TestLoadEnvVoiceEnvWinsOverAppEnv(t *testing.T) {
	clearEnv(t)
	t.Setenv(EnvAppEnv, "prod")
	t.Setenv(EnvName, "dev")

	dir := writeEnvFiles(t, map[string]string{
		".env":      "VOICE_LANG=en-US\n",
		".env.dev":  "VOICE_LANG=fr-FR\n",
		".env.prod": "VOICE_LANG=es-ES\n",
	})

	if _, err := LoadEnv(dir); err != nil {
		t.Fatal(err)
	}

	if got := os.Getenv(EnvLang); got != "fr-FR" {
		t.Errorf("%s = %q, VOICE_ENV doit primer sur APP_ENV", EnvLang, got)
	}
}

func TestParseEnvLine(t *testing.T) {
	values := map[string]string{"BASE": "C:/php"}

	cases := []struct {
		raw   string
		key   string
		value string
		ok    bool
	}{
		{raw: "", ok: false},
		{raw: "# commentaire", ok: false},
		{raw: "VOICE_LANG=fr-FR", key: "VOICE_LANG", value: "fr-FR", ok: true},
		{raw: "  VOICE_LANG = fr-FR  ", key: "VOICE_LANG", value: "fr-FR", ok: true},
		{raw: "export VOICE_LANG=fr-FR", key: "VOICE_LANG", value: "fr-FR", ok: true},
		{raw: `VOICE_PHP_BIN="${BASE}/php.exe"`, key: "VOICE_PHP_BIN", value: "C:/php/php.exe", ok: true},
		{raw: `VOICE_PHP_BIN='${BASE}/php.exe'`, key: "VOICE_PHP_BIN", value: "${BASE}/php.exe", ok: true},
		{raw: "VOICE_TIMEOUT=30s # borne", key: "VOICE_TIMEOUT", value: "30s", ok: true},
		{raw: "VOICE_LANG=", key: "VOICE_LANG", value: "", ok: true},
	}

	for _, tc := range cases {
		key, value, ok, err := parseEnvLine(tc.raw, values)

		if err != nil {
			t.Errorf("parseEnvLine(%q) : %v", tc.raw, err)
			continue
		}

		if ok != tc.ok || key != tc.key || value != tc.value {
			t.Errorf("parseEnvLine(%q) = (%q, %q, %t), attendu (%q, %q, %t)",
				tc.raw, key, value, ok, tc.key, tc.value, tc.ok)
		}
	}
}

func TestParseEnvLineErrors(t *testing.T) {
	for _, raw := range []string{"VOICE_LANG", "1VOICE=fr", "VOICE LANG=fr"} {
		if _, _, _, err := parseEnvLine(raw, nil); err == nil {
			t.Errorf("parseEnvLine(%q) : erreur attendue", raw)
		}
	}
}

func TestOptionsFromEnv(t *testing.T) {
	clearEnv(t)

	php := fakePHP(t)
	t.Setenv(EnvPHPBinary, php)
	t.Setenv(EnvPHPArgs, `-d memory_limit=512M -d "error_reporting=E_ALL"`)
	t.Setenv(EnvTimeout, "45s")
	t.Setenv(EnvVerbosity, "vv")
	t.Setenv(EnvNoANSI, "yes")
	t.Setenv(EnvInteractive, "0")

	opts, err := OptionsFromEnv(Options{})

	if err != nil {
		t.Fatal(err)
	}

	if opts.PHPBinary != php {
		t.Errorf("PHPBinary = %q, attendu %q", opts.PHPBinary, php)
	}

	want := []string{"-d", "memory_limit=512M", "-d", "error_reporting=E_ALL"}

	if !slices.Equal(opts.PHPArgs, want) {
		t.Errorf("PHPArgs = %v, attendu %v", opts.PHPArgs, want)
	}

	if opts.Timeout != 45*time.Second {
		t.Errorf("Timeout = %s, attendu 45s", opts.Timeout)
	}

	if opts.Verbosity != VerbosityVeryVerbose {
		t.Errorf("Verbosity = %d, attendu VerbosityVeryVerbose", opts.Verbosity)
	}

	if !opts.NoANSI {
		t.Error("NoANSI = false, attendu true")
	}

	if opts.Interactive {
		t.Error("Interactive = true, attendu false")
	}
}

func TestOptionsFromEnvKeepsExplicitValues(t *testing.T) {
	clearEnv(t)
	t.Setenv(EnvTimeout, "45s")
	t.Setenv(EnvVerbosity, "quiet")

	base := Options{Timeout: time.Second, Verbosity: VerbosityDebug}
	opts, err := OptionsFromEnv(base)

	if err != nil {
		t.Fatal(err)
	}

	if opts.Timeout != time.Second || opts.Verbosity != VerbosityDebug {
		t.Errorf("Options = %+v, les valeurs explicites doivent primer", opts)
	}
}

func TestOptionsFromEnvInvalidTimeout(t *testing.T) {
	clearEnv(t)
	t.Setenv(EnvTimeout, "trente secondes")

	if _, err := OptionsFromEnv(Options{}); err == nil {
		t.Error("erreur attendue pour une durée invalide")
	}
}

func TestUtteranceFromEnv(t *testing.T) {
	clearEnv(t)
	t.Setenv(EnvLang, "fr-FR")
	t.Setenv(EnvVoice, "fr-FR-DeniseNeural")

	u := UtteranceFromEnv(Utterance{Text: "Bonjour"})

	if u.Lang != "fr-FR" || u.Voice != "fr-FR-DeniseNeural" {
		t.Errorf("Utterance = %+v, attendu la langue et la voix de l'environnement", u)
	}

	explicit := UtteranceFromEnv(Utterance{Text: "Bonjour", Lang: "en-US", Voice: "en-US-AvaMultilingualNeural"})

	if explicit.Lang != "en-US" || explicit.Voice != "en-US-AvaMultilingualNeural" {
		t.Errorf("Utterance = %+v, les valeurs explicites doivent primer", explicit)
	}
}

func TestParseVerbosity(t *testing.T) {
	cases := map[string]Verbosity{
		"":             VerbosityNormal,
		"normal":       VerbosityNormal,
		"quiet":        VerbosityQuiet,
		"-v":           VerbosityVerbose,
		"VV":           VerbosityVeryVerbose,
		"vvv":          VerbosityDebug,
		"3":            VerbosityDebug,
		"very-verbose": VerbosityVeryVerbose,
	}

	for raw, want := range cases {
		got, err := ParseVerbosity(raw)

		if err != nil {
			t.Errorf("ParseVerbosity(%q) : %v", raw, err)
			continue
		}

		if got != want {
			t.Errorf("ParseVerbosity(%q) = %d, attendu %d", raw, got, want)
		}
	}

	if _, err := ParseVerbosity("bavard"); err == nil {
		t.Error("erreur attendue pour une verbosité inconnue")
	}
}

func TestEnvFileNames(t *testing.T) {
	if got := EnvFileNames(""); !slices.Equal(got, []string{".env", ".env.local"}) {
		t.Errorf("EnvFileNames(\"\") = %v", got)
	}

	want := []string{".env", ".env.local", ".env.prod", ".env.prod.local"}

	if got := EnvFileNames("prod"); !slices.Equal(got, want) {
		t.Errorf("EnvFileNames(\"prod\") = %v, attendu %v", got, want)
	}
}

func TestSplitArgs(t *testing.T) {
	cases := map[string][]string{
		"":                           nil,
		"   ":                        nil,
		"-d memory_limit=512M":       {"-d", "memory_limit=512M"},
		`-d "include_path=a;b" -n`:   {"-d", "include_path=a;b", "-n"},
		`-d 'auto_prepend_file=x y'`: {"-d", "auto_prepend_file=x y"},
	}

	for raw, want := range cases {
		if got := splitArgs(raw); !slices.Equal(got, want) {
			t.Errorf("splitArgs(%q) = %v, attendu %v", raw, got, want)
		}
	}
}
