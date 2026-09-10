export function quizQuestionBuilder({ initialCount = 3, oldQuestions = [] } = {}) {
    const blank = () => ({
        prompt: '',
        type: 'multiple_choice',
        options: ['', '', '', ''],
        correct_index: 0,
    });

    const fromOld = Array.isArray(oldQuestions) && oldQuestions.length
        ? oldQuestions.map((q) => ({
            prompt: q.prompt || '',
            type: q.type || 'multiple_choice',
            options: q.type === 'true_false'
                ? ['Verdadeiro', 'Falso']
                : [...(q.options || ['', ''])].concat(['', '', '', '']).slice(0, Math.max(2, (q.options || []).length || 4)),
            correct_index: Number(q.correct_index ?? 0),
        }))
        : Array.from({ length: initialCount }, blank);

    return {
        count: fromOld.length,
        questions: fromOld,
        syncCount() {
            const next = Math.min(50, Math.max(1, Number(this.count) || 1));
            this.count = next;
            while (this.questions.length < next) {
                this.questions.push(blank());
            }
            while (this.questions.length > next) {
                this.questions.pop();
            }
        },
        onTypeChange(question) {
            if (question.type === 'true_false') {
                question.options = ['Verdadeiro', 'Falso'];
                question.correct_index = 0;
            } else if (question.options.length < 2) {
                question.options = ['', '', '', ''];
            }
        },
        addOption(question) {
            if (question.options.length < 4) {
                question.options.push('');
            }
        },
        removeOption(question) {
            if (question.options.length > 2) {
                question.options.pop();
                if (question.correct_index >= question.options.length) {
                    question.correct_index = 0;
                }
            }
        },
    };
}

export function quizEventPlayer({ pollUrl, answerUrl, joinUrl, csrf, initialState }) {
    return {
        state: initialState || {},
        loading: false,
        error: null,
        timer: null,
        secondsLeft: initialState?.seconds_left ?? null,

        init() {
            this.join().finally(() => this.startPolling());
        },

        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
            }
        },

        async join() {
            try {
                const response = await fetch(joinUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (response.ok) {
                    this.state = await response.json();
                    this.secondsLeft = this.state.seconds_left;
                }
            } catch (e) {
                this.error = 'Não foi possível entrar no evento.';
            }
        },

        startPolling() {
            if (this.timer) {
                clearInterval(this.timer);
            }
            this.timer = setInterval(() => this.refresh(), 2000);
            setInterval(() => {
                if (this.secondsLeft !== null && this.secondsLeft > 0) {
                    this.secondsLeft -= 1;
                }
            }, 1000);
        },

        async refresh() {
            try {
                const response = await fetch(pollUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (! response.ok) {
                    return;
                }
                this.state = await response.json();
                this.secondsLeft = this.state.seconds_left;
                if (this.state.attempt?.finished || this.state.event?.status === 'closed') {
                    clearInterval(this.timer);
                    window.location.reload();
                }
            } catch (e) {
                // ignore transient poll errors
            }
        },

        async answer(index) {
            if (! this.state.question || this.loading) {
                return;
            }
            this.loading = true;
            this.error = null;
            try {
                const response = await fetch(answerUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        question_id: this.state.question.id,
                        selected_index: index,
                    }),
                });
                const data = await response.json().catch(() => ({}));
                if (! response.ok) {
                    this.error = data.message || Object.values(data.errors || {})[0]?.[0] || 'Não foi possível responder.';
                    this.loading = false;
                    return;
                }
                this.state = data;
                this.secondsLeft = data.seconds_left;
                if (data.attempt?.finished) {
                    clearInterval(this.timer);
                    window.location.reload();
                    return;
                }
            } catch (e) {
                this.error = 'Falha de conexão ao responder.';
            }
            this.loading = false;
        },
    };
}
