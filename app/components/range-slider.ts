export function initializeRange(range: HTMLInputElement): { value: number } {
    const controls = {
        range,
        container: $(range).parent().get(0) as HTMLDivElement,
        input: $(range).parent().find('input[type=number],input[type=text]').get(0) as HTMLInputElement,
        buttons: $(range).parent().find('button'),
        decrement: $(range).parent().find('button').get(0) as HTMLButtonElement,
        increment: $(range).parent().find('button').get(1) as HTMLButtonElement,
        initialValue: parseFloat(range.value),
        min: parseFloat(range.min),
        max: parseFloat(range.max),
        step: parseFloat(range.step),
    };

    function updateRange(value: number | string) {
        value = parseFloat(`${value}`);

        if (Number.isNaN(value)) {
            value = controls.initialValue;
        }
        controls.buttons.removeAttr('disabled');
        if (value >= controls.max) {
            $(controls.increment).attr('disabled', true);
        }

        if (value <= controls.min) {
            $(controls.decrement).attr('disabled', true);
        }

        if (value > controls.max) {
            value = controls.max;
        }

        if (value < controls.min) {
            value = controls.min;
        }

        if (value >= controls.min && value <= controls.max) {
            range.value = value.toFixed(1);
            controls.input.value = value.toFixed(1);
        }
    }

    $(range.form).on('reset', () => {
        updateRange(controls.initialValue);
    });

    $(controls.container).on('change keydown click', (event) => {
        const target = (event.target as HTMLElement).closest('input, button') as
            | HTMLInputElement
            | HTMLButtonElement
            | null;
        if (!target) {
            return;
        }

        if (event instanceof KeyboardEvent) {
            if (['button', 'number', 'text'].includes($(target).attr('type')) && ['Enter', ' '].includes(event.key)) {
                event.preventDefault();
                event.stopPropagation();
                if ('button' === $(target).attr('type')) {
                    updateRange(parseFloat(target.value) + parseFloat(range.value));
                    return;
                }
                updateRange(target.value);
                return;
            }
            if (controls.input === target && ['+', '-'].includes(event.key)) {
                event.preventDefault();
                event.stopPropagation();
                updateRange(parseFloat(event.key + range.step.toString()) + range.value);
                return;
            }
        }

        if (event instanceof PointerEvent && 'button' === $(target).attr('type')) {
            updateRange(parseFloat(target.value) + parseFloat(range.value));
        }

        if (['number', 'text', 'range'].includes(target.type) && 'change' === event.type) {
            updateRange(parseFloat(target.value));
        }
    });

    return {
        get value(): number {
            return parseFloat(range.value);
        },
        set value(value: number) {
            if (value >= controls.min && value <= controls.max) {
                updateRange(value);
            }
        },
    };
}
