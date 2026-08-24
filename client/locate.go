package client

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
)

// Variables d'environnement reconnues pour surcharger la localisation.
const (
	EnvPHPBinary   = "VOICE_PHP_BIN"
	EnvConsolePath = "VOICE_CONSOLE_PATH"
)

// LocatePHP renvoie le chemin de l'interpréteur PHP à utiliser.
//
// Ordre de résolution : valeur explicite, VOICE_PHP_BIN, PHP_BINARY,
// puis php.exe / php dans le PATH.
func LocatePHP(explicit string) (string, error) {
	candidates := []string{explicit, os.Getenv(EnvPHPBinary), os.Getenv("PHP_BINARY")}

	for _, candidate := range candidates {
		if candidate == "" {
			continue
		}

		// Un nom simple (« php ») reste résolu via le PATH.
		if filepath.Base(candidate) == candidate {
			if resolved, err := exec.LookPath(candidate); err == nil {
				return resolved, nil
			}
			continue
		}

		if info, err := os.Stat(candidate); err == nil && !info.IsDir() {
			return filepath.Clean(candidate), nil
		}
	}

	names := []string{"php"}
	if runtime.GOOS == "windows" {
		names = []string{"php.exe", "php"}
	}

	for _, name := range names {
		if resolved, err := exec.LookPath(name); err == nil {
			return resolved, nil
		}
	}

	return "", fmt.Errorf("%w: aucun interpréteur trouvé (essayez %s ou Options.PHPBinary)", ErrPHPNotFound, EnvPHPBinary)
}

// LocateConsole renvoie le chemin du script bin/console du projet voice.
//
// Ordre de résolution : valeur explicite, VOICE_CONSOLE_PATH, puis remontée
// arborescente depuis le répertoire courant et depuis le répertoire de
// l'exécutable, à la recherche d'un couple bin/console + composer.json.
func LocateConsole(explicit string) (string, error) {
	for _, candidate := range []string{explicit, os.Getenv(EnvConsolePath)} {
		if candidate == "" {
			continue
		}

		abs, err := filepath.Abs(candidate)

		if err != nil {
			return "", fmt.Errorf("%w: %s: %w", ErrConsoleNotFound, candidate, err)
		}

		if info, err := os.Stat(abs); err != nil || info.IsDir() {
			return "", fmt.Errorf("%w: %s n'est pas un fichier lisible", ErrConsoleNotFound, abs)
		}
		return abs, nil
	}

	var roots []string

	if cwd, err := os.Getwd(); err == nil {
		roots = append(roots, cwd)
	}

	if exe, err := os.Executable(); err == nil {
		roots = append(roots, filepath.Dir(exe))
	}

	for _, root := range roots {
		if found, ok := searchUpward(root); ok {
			return found, nil
		}
	}

	return "", fmt.Errorf("%w: bin/console introuvable (essayez %s ou Options.ConsolePath)", ErrConsoleNotFound, EnvConsolePath)
}

// searchUpward remonte de dir vers la racine en cherchant un projet voice.
func searchUpward(dir string) (string, bool) {
	dir, err := filepath.Abs(dir)

	if err != nil {
		return "", false
	}

	for {
		console := filepath.Join(dir, "bin", "console")

		if isProjectRoot(dir, console) {
			return console, true
		}

		parent := filepath.Dir(dir)

		if parent == dir {
			return "", false
		}
		dir = parent
	}
}

// isProjectRoot valide qu'un bin/console appartient bien à un projet PHP.
func isProjectRoot(dir, console string) bool {
	if info, err := os.Stat(console); err != nil || info.IsDir() {
		return false
	}

	if info, err := os.Stat(filepath.Join(dir, "composer.json")); err != nil || info.IsDir() {
		return false
	}
	return true
}

// ProjectRoot renvoie la racine du projet à partir du chemin de bin/console.
func ProjectRoot(console string) string {
	return filepath.Dir(filepath.Dir(console))
}
