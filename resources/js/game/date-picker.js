export function miniDatePicker(initialValue = '') {
    const selected = parseIso(initialValue) ?? startOfToday();

    return {
        open: false,
        value: toIso(selected),
        viewYear: selected.getFullYear(),
        viewMonth: selected.getMonth(),
        weekdays: ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'],
        get label() {
            const date = parseIso(this.value);

            return date ? date.toLocaleDateString('pt-BR') : 'Escolher data';
        },
        get monthLabel() {
            const label = new Date(this.viewYear, this.viewMonth, 1)
                .toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });

            return label.charAt(0).toUpperCase() + label.slice(1);
        },
        get days() {
            const firstWeekday = new Date(this.viewYear, this.viewMonth, 1).getDay();
            const count = new Date(this.viewYear, this.viewMonth + 1, 0).getDate();

            return [
                ...Array.from({ length: firstWeekday }, () => null),
                ...Array.from({ length: count }, (_, index) => index + 1),
            ];
        },
        toggle() {
            if (!this.open) {
                const current = parseIso(this.value);

                if (current) {
                    this.viewYear = current.getFullYear();
                    this.viewMonth = current.getMonth();
                }
            }

            this.open = ! this.open;
        },
        previousMonth() {
            if (this.viewMonth === 0) {
                this.viewMonth = 11;
                this.viewYear -= 1;

                return;
            }

            this.viewMonth -= 1;
        },
        nextMonth() {
            if (this.viewMonth === 11) {
                this.viewMonth = 0;
                this.viewYear += 1;

                return;
            }

            this.viewMonth += 1;
        },
        select(day) {
            this.value = toIso(new Date(this.viewYear, this.viewMonth, day));
            this.open = false;
        },
        isSelected(day) {
            return this.value === toIso(new Date(this.viewYear, this.viewMonth, day));
        },
        isToday(day) {
            return toIso(startOfToday()) === toIso(new Date(this.viewYear, this.viewMonth, day));
        },
    };
}

function parseIso(value) {
    if (typeof value !== 'string' || ! /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return date;
}

function startOfToday() {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function toIso(date) {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}
