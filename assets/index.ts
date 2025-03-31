// import styles (fonts, framework, helpers, app overrides)
import 'unfonts.css';
import './styles/tw.css';
import './styles/mini.scss';
import './main.css';


(() => {

    const route = document.body.dataset.route ?? "/";

    (() => {
        const documentElement = document.documentElement,
            lightMode: MediaQueryList = globalThis.matchMedia("(prefers-color-scheme: light)"),
            darkModeSwitch = document.getElementById('dark-mode-switch') as HTMLInputElement;

        function toggleDarkMode(checked: boolean) {
            checked ? documentElement.classList.add('dark') : documentElement.classList.remove('dark');
        }

        addEventListener('storage', (event: StorageEvent) => {
            if (event.storageArea === localStorage && event.key === 'piper:dark' && event.newValue !== null) {
                toggleDarkMode(darkModeSwitch.checked = JSON.parse(event.newValue));
            }
        });
        darkModeSwitch.addEventListener('change', () => {
            toggleDarkMode(darkModeSwitch.checked);
            localStorage.setItem('piper:dark', JSON.stringify(darkModeSwitch.checked));
        });
        if (localStorage.getItem('piper:dark') !== null) {
            toggleDarkMode(darkModeSwitch.checked = JSON.parse(localStorage.getItem('piper:dark') as string));
        } else if (!lightMode.matches) {
            toggleDarkMode(darkModeSwitch.checked = true);
        }

    })();


    if ('/' == route) {


        let url: string = '';

        const form = document.querySelector('form') as HTMLFormElement,
            langInput = form.elements.namedItem('lang') as HTMLSelectElement,
            voiceInput = form.elements.namedItem('voice') as HTMLSelectElement,
            voiceText = form.elements.namedItem('text') as HTMLTextAreaElement,
            submitButton = form.elements.namedItem('submitButton') as HTMLButtonElement,
            audioElement = document.querySelector('audio') as HTMLAudioElement,
            voiceItems = new Map<string, HTMLOptGroupElement>();

        submitButton.disabled = true;

        for (let group of [...voiceInput.querySelectorAll('optgroup')]) {
            voiceItems.set(group.getAttribute('label') as string, group);
        }


        langInput.addEventListener('change', () => {
            submitButton.disabled = true;
            voiceInput.selectedIndex = 0;
            for (let [lang, group] of voiceItems) {
                group.remove();
                if (!langInput.value || langInput.value === lang) {
                    voiceInput.appendChild(group);
                }
            }
        });

        voiceText.addEventListener('input', () => {
            submitButton.disabled = !voiceText.value.trim() && !voiceInput.value;
            console.debug(voiceText.value);
        });


        audioElement.addEventListener('pause', () => {
            URL.revokeObjectURL(url);
        });

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            audioElement.pause();

            fetch(('.' + voiceInput.value), {
                method: 'POST',
                body: JSON.stringify({
                    text: voiceText.value,
                }),
                headers: {"Content-Type": "application/json",}
            })
                .then(response => response.blob())
                .then(blob => {
                    audioElement.setAttribute('src', url = URL.createObjectURL(blob));
                    return audioElement.play();
                })
                .catch((err) => {
                    console.error(err);
                });

        })
    }
})()
