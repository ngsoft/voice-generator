// Package client pilote la commande PHP « bin/console speak » du projet voice
// en la lançant dans un sous-processus php.
//
// Aucun shell n'est utilisé : les arguments sont passés tels quels à php, ce qui
// évite tout problème d'échappement sur du texte arbitraire (guillemets, accents,
// retours à la ligne).
package client

import (
	"bytes"
	"context"
	"io"
	"os"
	"os/exec"
	"strings"
	"time"
)

// Valeurs par défaut alignées sur SpeakCommand::configure().
const (
	DefaultLang  = "en-US"
	DefaultVoice = "en-US-AvaMultilingualNeural"
)

// Verbosity reprend les niveaux de verbosité de Symfony Console.
type Verbosity int

const (
	VerbosityNormal Verbosity = iota
	VerbosityQuiet
	VerbosityVerbose
	VerbosityVeryVerbose
	VerbosityDebug
)

// flag renvoie l'option globale correspondante, ou une chaîne vide.
func (v Verbosity) flag() string {
	switch v {
	case VerbosityQuiet:
		return "--quiet"
	case VerbosityVerbose:
		return "-v"
	case VerbosityVeryVerbose:
		return "-vv"
	case VerbosityDebug:
		return "-vvv"
	default:
		return ""
	}
}

// Utterance décrit ce qui doit être vocalisé.
//
// Elle reflète les entrées de la commande speak : l'argument « text » et les
// options « --lang » et « --voice ». Lang et Voice vides retombent sur les
// valeurs par défaut de la commande.
type Utterance struct {
	Text  string
	Lang  string
	Voice string
}

// Options configure le lancement du sous-processus.
type Options struct {
	// PHPBinary est le chemin de php.exe. Vide : résolu par LocatePHP.
	PHPBinary string
	// ConsolePath est le chemin de bin/console. Vide : résolu par LocateConsole.
	ConsolePath string
	// WorkingDir est le répertoire de travail. Vide : la racine du projet
	// (parent de bin/), ce dont dépend le chemin relatif bin/console utilisé
	// par la commande pour se relancer sous WSL.
	WorkingDir string
	// PHPArgs sont des arguments insérés avant le script, par exemple
	// []string{"-d", "memory_limit=512M"}.
	PHPArgs []string
	// Env remplace l'environnement du sous-processus. Vide : celui du parent.
	Env []string
	// Timeout borne la durée d'exécution. Zéro : aucune limite.
	Timeout time.Duration
	// Verbosity pilote --quiet / -v / -vv / -vvv.
	Verbosity Verbosity
	// Interactive laisse la commande poser des questions. Faux : --no-interaction.
	Interactive bool
	// NoANSI ajoute --no-ansi (utile quand la sortie est journalisée).
	NoANSI bool
	// Stdout et Stderr reçoivent la sortie en flux, en plus de la capture
	// toujours restituée dans Result et dans ExitError. Nil : capture seule.
	Stdout io.Writer
	Stderr io.Writer
}

// Client lance la commande speak. Il est réutilisable et sûr en usage concurrent.
type Client struct {
	opts    Options
	php     string
	console string
	workDir string
}

// New résout php et bin/console une fois pour toutes et renvoie un Client prêt.
func New(opts Options) (*Client, error) {
	php, err := LocatePHP(opts.PHPBinary)

	if err != nil {
		return nil, err
	}

	console, err := LocateConsole(opts.ConsolePath)

	if err != nil {
		return nil, err
	}

	workDir := opts.WorkingDir

	if workDir == "" {
		workDir = ProjectRoot(console)
	}

	return &Client{opts: opts, php: php, console: console, workDir: workDir}, nil
}

// PHPBinary renvoie l'interpréteur retenu.
func (c *Client) PHPBinary() string { return c.php }

// ConsolePath renvoie le script retenu.
func (c *Client) ConsolePath() string { return c.console }

// WorkingDir renvoie le répertoire de travail du sous-processus.
func (c *Client) WorkingDir() string { return c.workDir }

// Args construit la ligne de commande complète, exécutable inclus.
//
// Les options précèdent le texte, séparé par « -- », pour qu'un texte commençant
// par un tiret ne soit pas interprété comme une option par Symfony Console.
// Exposé pour le diagnostic et les tests.
func (c *Client) Args(u Utterance) []string {
	lang := u.Lang

	if lang == "" {
		lang = DefaultLang
	}

	voice := u.Voice

	if voice == "" {
		voice = DefaultVoice
	}

	args := []string{c.php}
	args = append(args, c.opts.PHPArgs...)
	args = append(args, c.console, "speak", "--lang", lang, "--voice", voice)

	if !c.opts.Interactive {
		args = append(args, "--no-interaction")
	}

	if c.opts.NoANSI {
		args = append(args, "--no-ansi")
	}

	if flag := c.opts.Verbosity.flag(); flag != "" {
		args = append(args, flag)
	}
	return append(args, "--", u.Text)
}

// Result rassemble l'issue d'un appel réussi.
type Result struct {
	// Command est la ligne de commande lancée.
	Command []string
	// ExitCode est le code de sortie (0 en cas de succès).
	ExitCode int
	// Stdout et Stderr sont toujours la sortie capturée, y compris si
	// Options.Stdout / Options.Stderr reçoivent le flux en parallèle.
	Stdout string
	Stderr string
	// Duration est le temps passé dans le sous-processus.
	Duration time.Duration
}

// Speak vocalise l'énoncé et attend la fin de la lecture.
//
// Une sortie non nulle de php produit une *ExitError contenant le code, stdout et stderr.
func (c *Client) Speak(ctx context.Context, u Utterance) (*Result, error) {
	if strings.TrimSpace(u.Text) == "" {
		return nil, ErrEmptyText
	}

	if ctx == nil {
		ctx = context.Background()
	}

	if c.opts.Timeout > 0 {
		var cancel context.CancelFunc
		ctx, cancel = context.WithTimeout(ctx, c.opts.Timeout)
		defer cancel()
	}

	args := c.Args(u)

	cmd := exec.CommandContext(ctx, args[0], args[1:]...)
	cmd.Dir = c.workDir

	if len(c.opts.Env) > 0 {
		cmd.Env = c.opts.Env
	}

	var stdout, stderr bytes.Buffer

	if c.opts.Stdout != nil {
		cmd.Stdout = io.MultiWriter(c.opts.Stdout, &stdout)
	} else {
		cmd.Stdout = &stdout
	}

	if c.opts.Stderr != nil {
		cmd.Stderr = io.MultiWriter(c.opts.Stderr, &stderr)
	} else {
		cmd.Stderr = &stderr
	}

	start := time.Now()
	err := cmd.Run()
	elapsed := time.Since(start)

	result := &Result{
		Command:  args,
		ExitCode: cmd.ProcessState.ExitCode(),
		Stdout:   stdout.String(),
		Stderr:   stderr.String(),
		Duration: elapsed,
	}

	if err != nil {
		return result, &ExitError{
			Command:  args,
			ExitCode: result.ExitCode,
			Stdout:   result.Stdout,
			Stderr:   result.Stderr,
			Err:      err,
		}
	}
	return result, nil
}

// Speak est un raccourci pour un appel unique : il construit un Client jetable.
func Speak(ctx context.Context, u Utterance, opts Options) (*Result, error) {
	c, err := New(opts)

	if err != nil {
		return nil, err
	}
	return c.Speak(ctx, u)
}

// InheritStdio renvoie des Options branchées sur la sortie du processus courant.
func InheritStdio(opts Options) Options {
	opts.Stdout, opts.Stderr = os.Stdout, os.Stderr
	return opts
}
