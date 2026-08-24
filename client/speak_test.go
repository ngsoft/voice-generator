package client

import (
	"context"
	"errors"
	"os"
	"path/filepath"
	"runtime"
	"slices"
	"strings"
	"testing"
)

// fakeProject crée un arbre minimal contenant bin/console et composer.json.
func fakeProject(t *testing.T) string {
	t.Helper()

	root := t.TempDir()

	if err := os.MkdirAll(filepath.Join(root, "bin"), 0o755); err != nil {
		t.Fatal(err)
	}

	for _, name := range []string{filepath.Join("bin", "console"), "composer.json"} {
		if err := os.WriteFile(filepath.Join(root, name), []byte("{}"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	return root
}

// fakePHP crée un exécutable bidon servant d'interpréteur.
func fakePHP(t *testing.T) string {
	t.Helper()

	name := "php"

	if runtime.GOOS == "windows" {
		name = "php.exe"
	}

	path := filepath.Join(t.TempDir(), name)

	if err := os.WriteFile(path, []byte("#!/bin/sh\nexit 0\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	return path
}

func newTestClient(t *testing.T, opts Options) *Client {
	t.Helper()

	root := fakeProject(t)
	opts.PHPBinary = fakePHP(t)
	opts.ConsolePath = filepath.Join(root, "bin", "console")

	c, err := New(opts)

	if err != nil {
		t.Fatalf("New: %v", err)
	}
	return c
}

func TestArgsDefaults(t *testing.T) {
	c := newTestClient(t, Options{})
	args := c.Args(Utterance{Text: "Hello world"})

	if got := args[0]; got != c.PHPBinary() {
		t.Errorf("premier argument = %q, attendu %q", got, c.PHPBinary())
	}

	if got := args[1]; got != c.ConsolePath() {
		t.Errorf("script = %q, attendu %q", got, c.ConsolePath())
	}

	if got := args[2]; got != "speak" {
		t.Errorf("commande = %q, attendu speak", got)
	}

	joined := strings.Join(args, " ")

	for _, want := range []string{"--lang " + DefaultLang, "--voice " + DefaultVoice, "--no-interaction"} {
		if !strings.Contains(joined, want) {
			t.Errorf("%q absent de %q", want, joined)
		}
	}

	if args[len(args)-2] != "--" || args[len(args)-1] != "Hello world" {
		t.Errorf("le texte doit être en dernier après « -- » : %q", args[len(args)-3:])
	}
}

func TestArgsOptions(t *testing.T) {
	c := newTestClient(t, Options{
		PHPArgs:     []string{"-d", "memory_limit=512M"},
		Verbosity:   VerbosityVeryVerbose,
		NoANSI:      true,
		Interactive: true,
	})

	args := c.Args(Utterance{Text: "Bonjour", Lang: "fr-FR", Voice: "fr-FR-DeniseNeural"})

	for _, want := range []string{"-d", "memory_limit=512M", "-vv", "--no-ansi", "fr-FR", "fr-FR-DeniseNeural"} {
		if !slices.Contains(args, want) {
			t.Errorf("%q absent de %v", want, args)
		}
	}

	if slices.Contains(args, "--no-interaction") {
		t.Errorf("--no-interaction ne doit pas être passé en mode interactif : %v", args)
	}

	if args[1] != "-d" || args[2] != "memory_limit=512M" {
		t.Errorf("PHPArgs doivent précéder le script : %v", args)
	}
}

func TestArgsTextWithLeadingDash(t *testing.T) {
	c := newTestClient(t, Options{})
	args := c.Args(Utterance{Text: "--version est une option"})

	if args[len(args)-2] != "--" {
		t.Fatalf("séparateur « -- » attendu avant le texte : %v", args)
	}
}

func TestSpeakRejectsEmptyText(t *testing.T) {
	c := newTestClient(t, Options{})

	for _, text := range []string{"", "   ", "\n\t"} {
		if _, err := c.Speak(context.Background(), Utterance{Text: text}); !errors.Is(err, ErrEmptyText) {
			t.Errorf("texte %q : erreur = %v, attendu ErrEmptyText", text, err)
		}
	}
}

func TestWorkingDirDefaultsToProjectRoot(t *testing.T) {
	root := fakeProject(t)
	console := filepath.Join(root, "bin", "console")

	c, err := New(Options{PHPBinary: fakePHP(t), ConsolePath: console})

	if err != nil {
		t.Fatal(err)
	}

	if c.WorkingDir() != root {
		t.Errorf("WorkingDir = %q, attendu %q", c.WorkingDir(), root)
	}
}

func TestLocateConsoleFromEnv(t *testing.T) {
	root := fakeProject(t)
	console := filepath.Join(root, "bin", "console")
	t.Setenv(EnvConsolePath, console)

	got, err := LocateConsole("")

	if err != nil {
		t.Fatal(err)
	}

	if got != console {
		t.Errorf("LocateConsole = %q, attendu %q", got, console)
	}
}

func TestLocateConsoleSearchesUpward(t *testing.T) {
	root := fakeProject(t)
	deep := filepath.Join(root, "src", "Command")

	if err := os.MkdirAll(deep, 0o755); err != nil {
		t.Fatal(err)
	}

	t.Setenv(EnvConsolePath, "")
	t.Chdir(deep)

	got, err := LocateConsole("")

	if err != nil {
		t.Fatal(err)
	}

	if want := filepath.Join(root, "bin", "console"); got != want {
		t.Errorf("LocateConsole = %q, attendu %q", got, want)
	}
}

func TestLocateConsoleMissing(t *testing.T) {
	t.Setenv(EnvConsolePath, "")
	t.Chdir(t.TempDir())

	if _, err := LocateConsole(""); !errors.Is(err, ErrConsoleNotFound) {
		t.Errorf("erreur = %v, attendu ErrConsoleNotFound", err)
	}
}

func TestLocatePHPFromEnv(t *testing.T) {
	php := fakePHP(t)
	t.Setenv(EnvPHPBinary, php)

	got, err := LocatePHP("")

	if err != nil {
		t.Fatal(err)
	}

	if got != php {
		t.Errorf("LocatePHP = %q, attendu %q", got, php)
	}
}

func TestVerbosityFlags(t *testing.T) {
	cases := map[Verbosity]string{
		VerbosityNormal:      "",
		VerbosityQuiet:       "--quiet",
		VerbosityVerbose:     "-v",
		VerbosityVeryVerbose: "-vv",
		VerbosityDebug:       "-vvv",
	}

	for level, want := range cases {
		if got := level.flag(); got != want {
			t.Errorf("Verbosity(%d).flag() = %q, attendu %q", level, got, want)
		}
	}
}
