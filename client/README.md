# voice/client — client Go pour `bin/console speak`

Client Go qui lance `php.exe <projet>/bin/console speak` en sous-processus et relaie l'argument `text` ainsi que les options `--lang` et `--voice` de
[`SpeakCommand`](../src/Command/SpeakCommand.php).

Aucun shell n'est employé : les arguments sont transmis directement à `php`, donc le texte peut contenir guillemets, accents ou retours à la ligne sans échappement. Les options précèdent le texte, séparé par `--`, pour qu'un texte commençant par un tiret ne soit pas pris pour une option.

## Bibliothèque

```go
package main

import (
	"context"
	"log"

	client "voice/client"
)

func main() {
	c, err := client.New(client.InheritStdio(client.Options{
		Verbosity: client.VerbosityNormal,
	}))

	if err != nil {
		log.Fatal(err)
	}

	result, err := c.Speak(context.Background(), client.Utterance{
		Text:  "The Go client is ready",
		Voice: "en-US-BrianMultilingualNeural",
		Lang:  "en-US",
	})

	if err != nil {
		log.Fatal(err)
	}
	log.Printf("terminé en %s (code %d)", result.Duration, result.ExitCode)
}
```

Pour un appel unique, `client.Speak(ctx, utterance, opts)` construit un `Client`
jetable.

### Configuration

La configuration se lit par ordre de priorité décroissante :

1. option Go ou drapeau de la CLI ;
2. `speak.json` posé à côté de l'exécutable ;
3. variable d'environnement réelle ;
4. fichiers `.env` ;
5. valeurs par défaut du paquet.

#### `speak.json`

Un binaire distribué seul se configure par un `speak.json` voisin — rien n'est compilé dans l'exécutable, tout est lu au lancement. Le fichier est cherché dans
`VOICE_CONFIG`, puis à côté de l'exécutable, puis dans le répertoire courant ;
[speak.json.dist](speak.json.dist) sert de modèle.

```json
{
    "lang": "fr-FR",
    "voice": "fr-FR-DeniseNeural",
    "consolePath": "D:/dev/php8/apis/voice/bin/console",
    "verbosity": "quiet"
}
```

Les champs disponibles sont `env`, `phpBinary`, `consolePath`, `workingDir`,
`phpArgs`, `lang`, `voice`, `timeout`, `verbosity`, `noAnsi`, `interactive`, plus
`$comment` et `$schema` sans effet. Un champ présent surcharge la variable d'environnement correspondante, y compris celle d'un `.env` ; un champ absent laisse l'environnement décider — d'où la distinction entre `"interactive": false`
et une clé omise.

L'absence de fichier n'est pas une erreur. En revanche un fichier présent mais JSON malformé, clé inconnue, durée ou verbosité invalide arrête l'exécution avec le chemin fautif, plutôt que d'appliquer une configuration à moitié comprise.

`-config <chemin>` cible un fichier précis, `-no-config` l'ignore.

#### Fichiers `.env`

Ils sont lus dans le premier répertoire qui en contient (`VOICE_ENV_DIR`, sinon le répertoire courant, sinon celui de l'exécutable). Dans un répertoire, les fichiers se cumulent, le dernier gagnant :

```
.env                  valeurs par défaut, versionnées
.env.local            surcharges locales, non versionnées
.env.$VOICE_ENV       défauts propres à un environnement, non versionnés
.env.$VOICE_ENV.local surcharges propres à un environnement, non versionnées
```

Tout ce qui est chargé est hérité par le sous-processus `php`, d'où le sélecteur dédié `VOICE_ENV` (`APP_ENV` sert de repli) : `APP_ENV` a sa propre signification côté PHP et ne doit pas être imposé depuis le client.

| Variable             | Rôle                                                     |
|----------------------|----------------------------------------------------------|
| `VOICE_CONFIG`       | chemin du fichier `speak.json`                           |
| `VOICE_ENV`          | suffixe des fichiers spécifiques à un environnement      |
| `VOICE_ENV_DIR`      | répertoire des fichiers `.env`                           |
| `VOICE_PHP_BIN`      | interpréteur PHP                                         |
| `VOICE_CONSOLE_PATH` | chemin de `bin/console`                                  |
| `VOICE_WORKING_DIR`  | répertoire de travail du sous-processus                  |
| `VOICE_PHP_ARGS`     | arguments insérés avant le script (guillemets respectés) |
| `VOICE_LANG`         | langue par défaut de l'énoncé                            |
| `VOICE_VOICE`        | voix par défaut                                          |
| `VOICE_TIMEOUT`      | durée maximale (`30s`, `2m`…)                            |
| `VOICE_VERBOSITY`    | `quiet`, `normal`, `v`, `vv`, `vvv`                      |
| `VOICE_NO_ANSI`      | booléen (`true`/`yes`/`on`…)                             |
| `VOICE_INTERACTIVE`  | booléen                                                  |

Le format accepté couvre les commentaires, le préfixe `export`, les guillemets simples (littéraux) et doubles, et l'interpolation `${VAR}` / `$VAR`.

#### En bibliothèque

Rien n'est implicite : aucun fichier n'est lu à l'import. L'ordre reproduit celui de la CLI.

```go
cfg, configFile, err := client.LoadConfig()      // speak.json, nil si absent

if err != nil {
	log.Fatal(err)
}

// Le nom d'environnement d'abord, pour que la config choisisse les .env chargés.
if err := cfg.ApplyEnvName(); err != nil {
	log.Fatal(err)
}

envFiles, err := client.LoadEnv()                // ne touche pas aux variables réelles

if err != nil {
	log.Fatal(err)
}

if err := cfg.Apply(); err != nil {              // la config écrase l'environnement
	log.Fatal(err)
}

opts, err := client.OptionsFromEnv(client.Options{})   // les champs non nuls sont conservés

if err != nil {
	log.Fatal(err)
}

c, err := client.New(client.InheritStdio(opts))
utterance := client.UtteranceFromEnv(client.Utterance{Text: "Hello world"})
```

`LoadEnv` ne remplace jamais une variable déjà présente dans l'environnement réel et renvoie la liste des fichiers retenus ; `Config.Apply` écrase, y compris ce que les `.env` viennent de poser. Les méthodes de `*Config` acceptent un récepteur nil, donc une configuration absente ne demande aucun test préalable.

### Options

| Champ             | Rôle                                                                                                                                           |
|-------------------|------------------------------------------------------------------------------------------------------------------------------------------------|
| `PHPBinary`       | chemin de `php.exe` ; vide → `VOICE_PHP_BIN`, `PHP_BINARY`, puis PATH                                                                          |
| `ConsolePath`     | chemin de `bin/console` ; vide → `VOICE_CONSOLE_PATH`, puis remontée arborescente (`bin/console` + `composer.json`)                            |
| `WorkingDir`      | répertoire de travail ; vide → racine du projet, dont dépend le chemin relatif `bin/console` utilisé par la commande pour se relancer sous WSL |
| `PHPArgs`         | arguments insérés avant le script, ex. `-d memory_limit=512M`                                                                                  |
| `Env`             | environnement de remplacement du sous-processus                                                                                                |
| `Timeout`         | borne de durée ; 0 = illimité                                                                                                                  |
| `Verbosity`       | `--quiet`, `-v`, `-vv`, `-vvv`                                                                                                                 |
| `Interactive`     | faux (défaut) → `--no-interaction`                                                                                                             |
| `NoANSI`          | ajoute `--no-ansi`                                                                                                                             |
| `Stdout`/`Stderr` | flux de sortie en parallèle de la capture ; nil → capture seule, restituée dans `Result` et `ExitError`                                        |

### Erreurs

- `ErrEmptyText` — texte vide ou uniquement des espaces (vérifié avant lancement).
- `ErrPHPNotFound`, `ErrConsoleNotFound` — résolution impossible.
- `*ExitError` — sortie non nulle de `php` : contient `Command`, `ExitCode`, `Stdout` et `Stderr`.

## CLI

```bash
go build -o speak.exe ./cmd/speak
```

```bash
./speak.exe --voice en-US-BrianMultilingualNeural "The Go client is ready"
```

Options : `-lang`, `-voice`, `-php`, `-console`, `-cwd`, `-timeout`,
`-verbosity <quiet|normal|v|vv|vvv>`, `-q`, `-no-ansi`, `-interactive`,
`-config`, `-no-config`, `-env-dir`, `-no-env`, `-json`, `-dry-run`.

Le texte se donne en arguments, ou `-` pour le lire sur l'entrée standard :

```bash
echo "Depuis l'entrée standard" | ./speak.exe -
```

`-dry-run` affiche la commande résolue sans l'exécuter, avec le `speak.json` et les fichiers `.env` retenus ; `-json` renvoie le résultat structuré (commande, code de sortie, sorties, durée). Sans `-json`, stdout et stderr de `php` sont affichés s'ils ne sont pas vides. Le code de sortie de la CLI reprend celui de `php`.

Pour une exécution totalement silencieuse, `-q` ou `VOICE_VERBOSITY=quiet`
transmet `--quiet` à la commande PHP.

## Tests

```bash
go test ./...
```

Les tests couvrent la construction des arguments, la résolution de `php` et de
`bin/console`, le chargement et la précédence des fichiers `.env` et de
`speak.json`, et n'exécutent aucun véritable processus PHP.
