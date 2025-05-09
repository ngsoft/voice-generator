// import styles (fonts, framework, helpers, app overrides)

import 'unfonts.css';
import 'tom-select/dist/css/tom-select.bootstrap4.min.css';
import './styles/tw.css';
import './styles/mini.scss';
import './main.css';


(() => {
    const route = document.body.dataset.route ?? '/';

    (() => {
        const documentElement = document.documentElement,
            lightMode: MediaQueryList = globalThis.matchMedia('(prefers-color-scheme: light)'),
            darkModeSwitch = document.getElementById('dark-mode-switch') as HTMLInputElement;


        function toggleDarkMode(checked: boolean) {
            checked ? documentElement.classList.add('dark') : documentElement.classList.remove('dark');
        }

        addEventListener('storage', (event: StorageEvent) => {
            if (event.storageArea === localStorage && event.key === 'piper:dark' && event.newValue !== null) {
                toggleDarkMode((darkModeSwitch.checked = JSON.parse(event.newValue)));
            }
        });
        darkModeSwitch.addEventListener('change', () => {
            toggleDarkMode(darkModeSwitch.checked);
            localStorage.setItem('piper:dark', JSON.stringify(darkModeSwitch.checked));
        });
        if (localStorage.getItem('piper:dark') !== null) {
            toggleDarkMode((darkModeSwitch.checked = JSON.parse(localStorage.getItem('piper:dark') as string)));
        } else if (!lightMode.matches) {
            toggleDarkMode((darkModeSwitch.checked = true));
        }
    })();

    if ('/' == route) {
        let url: string = '', filename: string = '';

        const form = document.querySelector('form') as HTMLFormElement,
            langInput = form.elements.namedItem('lang') as HTMLSelectElement,
            voiceInput = form.elements.namedItem('voice') as HTMLSelectElement,
            voiceText = form.elements.namedItem('text') as HTMLTextAreaElement,
            submitButton = form.elements.namedItem('submitButton') as HTMLButtonElement,
            audioElement = document.querySelector('audio') as HTMLAudioElement,
            downloadButton = document.getElementById('download') as HTMLButtonElement,
            voiceItems = new Map<string, HTMLOptGroupElement>(),
            tomSelectOptions = {
                itemClass: '_item',
                maxOptions: null,
            };


        function revokeUrl() {
            if (url) {
                URL.revokeObjectURL(url);
                url = '';
            }
        }


        function createUrl(blob: Blob): string {
            revokeUrl();
            url = URL.createObjectURL(blob);
            toggleDownload();
            return url;
        }


        function toggleDownload() {
            downloadButton.disabled = filename.length === 0;
        }

        function updateGroup() {
            for (let [lang, group] of voiceItems) {
                group.remove();
                if (!langInput.value || langInput.value === lang) {
                    voiceInput.appendChild(group);
                }
            }
        }

        submitButton.disabled = !voiceText.value.trim() && !voiceInput.value;

        for (let group of [...voiceInput.querySelectorAll('optgroup')]) {
            voiceItems.set(group.getAttribute('label') as string, group);
        }

        updateGroup();

        // @ts-ignore
        (new TomSelect(langInput, tomSelectOptions));


        langInput.addEventListener('change', () => {
            submitButton.disabled = true;
            voiceInput.selectedIndex = 0;
            updateGroup();
        });

        voiceText.addEventListener('input', () => {
            submitButton.disabled = !voiceText.value.trim() && !voiceInput.value;
        });

        voiceInput.addEventListener('change', () => {
            submitButton.disabled = !voiceText.value.trim() && !voiceInput.value;
        });

        form.addEventListener('reset', () => {
            submitButton.disabled = true;
        });

        downloadButton.addEventListener('click', () => {
            if (filename && url) {
                const link = document.createElement('a');
                // link.setAttribute('class', 'visually-hidden');
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                requestAnimationFrame(() => {
                    link.remove();
                });
            }
        });


        form.addEventListener('submit', (e) => {
            e.preventDefault();

            audioElement.pause();

            fetch('.' + voiceInput.value, {
                method: 'POST',
                body: JSON.stringify({text: voiceText.value}),
                headers: {'Content-Type': 'application/json'},
            })
                .then((response) => {
                    filename = (/"(.+)"/.exec(response.headers.get('content-disposition') ?? '') ?? [])[1] ?? '';
                    return response.blob();
                })
                .then((blob) => {
                    audioElement.setAttribute('src', createUrl(blob));
                    return audioElement.play();
                })
                .catch((err) => {
                    console.error(err);
                });
        });


    }
})();
