package client

import (
	"errors"
	"fmt"
	"strings"
)

var (
	// ErrEmptyText signale un appel sans texte à vocaliser.
	ErrEmptyText = errors.New("client: le texte est vide")
	// ErrPHPNotFound signale un interpréteur PHP introuvable.
	ErrPHPNotFound = errors.New("client: php introuvable")
	// ErrConsoleNotFound signale un script bin/console introuvable.
	ErrConsoleNotFound = errors.New("client: bin/console introuvable")
)

// ExitError décrit l'échec du sous-processus PHP.
type ExitError struct {
	// Command est la ligne de commande effectivement lancée, à titre de diagnostic.
	Command []string
	// ExitCode est le code de sortie renvoyé par php (-1 si le processus n'a pas démarré).
	ExitCode int
	// Stderr contient la sortie d'erreur capturée, si elle ne fut pas redirigée.
	Stderr string
	// Err est l'erreur d'origine remontée par os/exec.
	Err error
}

func (e *ExitError) Error() string {
	var b strings.Builder

	fmt.Fprintf(&b, "client: %s a échoué (code %d)", strings.Join(e.Command, " "), e.ExitCode)

	if stderr := strings.TrimSpace(e.Stderr); stderr != "" {
		fmt.Fprintf(&b, ": %s", stderr)
	} else if e.Err != nil {
		fmt.Fprintf(&b, ": %s", e.Err)
	}
	return b.String()
}

func (e *ExitError) Unwrap() error { return e.Err }
