<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Area;
use App\Models\AreaBalance;
use App\Models\Enrollment;
use App\Models\EnrollmentCosmetic;
use App\Models\GameEvent;
use App\Models\GameEventAnswer;
use App\Models\GameEventAttempt;
use App\Models\GameEventQuestion;
use App\Models\SchoolClass;
use App\Models\ShopItem;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Support\CosmeticCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameEventService
{
    public function __construct(
        private GameLoopService $gameLoop,
        private CosmeticShopService $shop,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     kind: string,
     *     mode: string,
     *     starts_at?: ?string,
     *     ends_at?: ?string,
     *     question_seconds?: int,
     *     relics_per_correct?: int,
     *     seals_per_correct?: int,
     *     auras_per_correct?: int,
     *     weight?: int,
     *     max_score?: int,
     *     publish?: bool,
     *     questions: list<array{prompt: string, type: string, options: list<string>, correct_index: int}>,
     *     prize?: ?array{name: string, slot: string, price: int, currency: string, rarity: string, icon: string, css?: ?string, label?: ?string, combat_bonus_percent?: mixed}
     * }  $data
     */
    public function create(User $author, array $data, ?SchoolClass $class = null, ?Area $area = null): GameEvent
    {
        $kind = $data['kind'];
        $this->assertScope($kind, $class, $area);
        $questions = $this->normalizeQuestions($data['questions'] ?? []);

        return DB::transaction(function () use ($author, $data, $class, $area, $kind, $questions) {
            $activity = null;
            if ($kind === GameEvent::KIND_ACTIVITY) {
                $activity = $class->activities()->create([
                    'name' => $data['title'],
                    'type' => Activity::TYPE_EVENT,
                    'max_score' => (int) ($data['max_score'] ?? 100),
                    'weight' => (int) ($data['weight'] ?? 1),
                ]);
            }

            $prize = null;
            if (in_array($kind, [GameEvent::KIND_CLASS, GameEvent::KIND_REALM], true)) {
                $prizePayload = $data['prize'] ?? null;
                if (! is_array($prizePayload)) {
                    throw ValidationException::withMessages([
                        'prize' => 'Cadastre o item exclusivo do vencedor.',
                    ]);
                }

                $prizeClass = $kind === GameEvent::KIND_CLASS ? $class : null;
                if ($kind === GameEvent::KIND_REALM && $area) {
                    $prizePayload['area_id'] = $area->id;
                }

                $prize = $this->createPrizeItem($prizePayload, $prizeClass, $area);
            }

            $status = ! empty($data['publish'])
                ? ($data['mode'] === GameEvent::MODE_LIVE ? GameEvent::STATUS_SCHEDULED : GameEvent::STATUS_SCHEDULED)
                : GameEvent::STATUS_DRAFT;

            if (! empty($data['publish']) && $data['mode'] === GameEvent::MODE_WINDOW) {
                $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
                $status = $startsAt->lte(now()) ? GameEvent::STATUS_LIVE : GameEvent::STATUS_SCHEDULED;
            }

            $event = GameEvent::query()->create([
                'title' => $data['title'],
                'kind' => $kind,
                'mode' => $data['mode'],
                'status' => $status,
                'class_id' => $class?->id,
                'area_id' => $area?->id ?? $class?->area_id,
                'activity_id' => $activity?->id,
                'created_by' => $author->id,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'question_seconds' => (int) ($data['question_seconds'] ?? 30),
                'relics_per_correct' => (int) ($data['relics_per_correct'] ?? 0),
                'seals_per_correct' => (int) ($data['seals_per_correct'] ?? 0),
                'auras_per_correct' => (int) ($data['auras_per_correct'] ?? 0),
                'prize_item_id' => $prize?->id,
            ]);

            foreach ($questions as $index => $question) {
                $event->questions()->create([
                    'prompt' => $question['prompt'],
                    'type' => $question['type'],
                    'options' => $question['options'],
                    'correct_index' => $question['correct_index'],
                    'position' => $index,
                ]);
            }

            if ($status !== GameEvent::STATUS_DRAFT) {
                $this->notifyParticipants($event, 'event_open', 'Novo evento', $event->title.' está disponível.');
            }

            return $event->fresh(['questions', 'activity', 'prizeItem']);
        });
    }

    public function publish(GameEvent $event): GameEvent
    {
        if ($event->status !== GameEvent::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'event' => 'Este evento já foi publicado.',
            ]);
        }

        if ($event->questions()->count() < 1) {
            throw ValidationException::withMessages([
                'questions' => 'Cadastre ao menos uma pergunta.',
            ]);
        }

        $status = GameEvent::STATUS_SCHEDULED;
        if ($event->isWindowMode()) {
            $startsAt = $event->starts_at ?? now();
            if (! $event->starts_at) {
                $event->starts_at = $startsAt;
            }
            $status = $startsAt->lte(now()) ? GameEvent::STATUS_LIVE : GameEvent::STATUS_SCHEDULED;
        }

        $event->update(['status' => $status]);
        $this->notifyParticipants($event, 'event_open', 'Novo evento', $event->title.' está disponível.');

        return $event->fresh();
    }

    public function startLive(GameEvent $event): GameEvent
    {
        if (! $event->isLiveMode()) {
            throw ValidationException::withMessages([
                'event' => 'Só eventos ao vivo podem ser iniciados assim.',
            ]);
        }

        if (! in_array($event->status, [GameEvent::STATUS_DRAFT, GameEvent::STATUS_SCHEDULED], true)) {
            throw ValidationException::withMessages([
                'event' => 'Este evento não pode ser iniciado agora.',
            ]);
        }

        if ($event->questions()->count() < 1) {
            throw ValidationException::withMessages([
                'questions' => 'Cadastre ao menos uma pergunta.',
            ]);
        }

        $event->update([
            'status' => GameEvent::STATUS_LIVE,
            'starts_at' => $event->starts_at ?? now(),
            'current_question_index' => 0,
            'current_question_opened_at' => now(),
        ]);

        $this->notifyParticipants($event, 'event_live', 'Evento ao vivo', $event->title.' começou!');

        return $event->fresh();
    }

    public function tick(?GameEvent $event = null): int
    {
        $query = GameEvent::query()->where('status', GameEvent::STATUS_LIVE);
        if ($event) {
            $query->whereKey($event->id);
        }

        $advanced = 0;

        foreach ($query->with('questions')->get() as $liveEvent) {
            if ($this->advanceLiveIfDue($liveEvent)) {
                $advanced++;
            }

            if ($liveEvent->isWindowMode() && $liveEvent->ends_at && $liveEvent->ends_at->lte(now())) {
                $this->close($liveEvent);
                $advanced++;
            }
        }

        $scheduled = GameEvent::query()
            ->where('status', GameEvent::STATUS_SCHEDULED)
            ->where('mode', GameEvent::MODE_WINDOW)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->get();

        foreach ($scheduled as $scheduledEvent) {
            $scheduledEvent->update(['status' => GameEvent::STATUS_LIVE]);
            $advanced++;
        }

        $expiredWindows = GameEvent::query()
            ->where('status', GameEvent::STATUS_LIVE)
            ->where('mode', GameEvent::MODE_WINDOW)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($expiredWindows as $expired) {
            $this->close($expired);
            $advanced++;
        }

        return $advanced;
    }

    public function advanceLiveIfDue(GameEvent $event): bool
    {
        if (! $event->isLiveMode() || $event->status !== GameEvent::STATUS_LIVE) {
            return false;
        }

        if ($event->current_question_index === null || ! $event->current_question_opened_at) {
            return false;
        }

        $deadline = $event->current_question_opened_at->copy()->addSeconds($event->question_seconds);
        if ($deadline->gt(now())) {
            return false;
        }

        $next = (int) $event->current_question_index + 1;
        $total = $event->questions()->count();

        if ($next >= $total) {
            $this->close($event);

            return true;
        }

        $event->update([
            'current_question_index' => $next,
            'current_question_opened_at' => now(),
        ]);

        return true;
    }

    public function close(GameEvent $event): GameEvent
    {
        if ($event->isClosed()) {
            return $event;
        }

        return DB::transaction(function () use ($event) {
            $locked = GameEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                return $locked;
            }

            $locked->update([
                'status' => GameEvent::STATUS_CLOSED,
                'ends_at' => $locked->ends_at ?? now(),
                'current_question_opened_at' => null,
            ]);

            $openAttempts = GameEventAttempt::query()
                ->where('game_event_id', $locked->id)
                ->whereNull('finished_at')
                ->lockForUpdate()
                ->get();

            foreach ($openAttempts as $openAttempt) {
                $openAttempt->update([
                    'finished_at' => now(),
                    'current_question_position' => null,
                    'current_question_shown_at' => null,
                ]);

                if (! $openAttempt->rewards_granted) {
                    $student = $openAttempt->student;
                    $class = $openAttempt->schoolClass;
                    if ($student && $class) {
                        $this->grantAttemptRewards($locked, $openAttempt->fresh(), $student, $class);
                    }
                }
            }

            if ($locked->awardsPrize() && ! $locked->awarded_at) {
                $this->awardPrize($locked->fresh());
            }

            $this->notifyParticipants($locked, 'event_closed', 'Evento encerrado', $locked->title.' foi encerrado. Veja o ranking!');

            return $locked->fresh(['prizeItem']);
        });
    }

    public function startOrResumeAttempt(GameEvent $event, User $student, SchoolClass $class): GameEventAttempt
    {
        $this->assertStudentCanPlay($event, $student, $class);
        $this->tick($event);
        $event->refresh();

        if (! $event->isOpen() && ! ($event->status === GameEvent::STATUS_LIVE)) {
            throw ValidationException::withMessages([
                'event' => 'Este evento não está aberto para respostas.',
            ]);
        }

        if ($event->isWindowMode()) {
            if ($event->starts_at && $event->starts_at->gt(now())) {
                throw ValidationException::withMessages([
                    'event' => 'O evento ainda não começou.',
                ]);
            }
            if ($event->ends_at && $event->ends_at->lte(now())) {
                $this->close($event);
                throw ValidationException::withMessages([
                    'event' => 'O prazo deste evento acabou.',
                ]);
            }
        }

        $attempt = GameEventAttempt::query()->firstOrCreate(
            [
                'game_event_id' => $event->id,
                'student_id' => $student->id,
            ],
            [
                'class_id' => $class->id,
                'started_at' => now(),
            ],
        );

        if ($attempt->isFinished()) {
            return $attempt;
        }

        if ($event->isWindowMode() && $attempt->current_question_position === null) {
            $attempt->update([
                'current_question_position' => 0,
                'current_question_shown_at' => now(),
                'started_at' => $attempt->started_at ?? now(),
            ]);
        }

        return $attempt->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function playState(GameEvent $event, User $student, SchoolClass $class): array
    {
        $this->tick($event);
        $event->refresh()->load('questions');

        $attempt = GameEventAttempt::query()
            ->where('game_event_id', $event->id)
            ->where('student_id', $student->id)
            ->first();

        $answeredIds = $attempt
            ? $attempt->answers()->pluck('game_event_question_id')->all()
            : [];

        $payload = [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'kind' => $event->kind,
                'mode' => $event->mode,
                'status' => $event->status,
                'question_seconds' => $event->question_seconds,
                'question_count' => $event->questions->count(),
                'current_question_index' => $event->current_question_index,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
            ],
            'attempt' => $attempt ? [
                'id' => $attempt->id,
                'correct_count' => $attempt->correct_count,
                'correct_time_ms' => $attempt->correct_time_ms,
                'finished' => $attempt->isFinished(),
                'answered_count' => count($answeredIds),
            ] : null,
            'question' => null,
            'seconds_left' => null,
        ];

        if ($attempt?->isFinished() || $event->isClosed()) {
            return $payload;
        }

        if ($event->isLiveMode()) {
            if ($event->status !== GameEvent::STATUS_LIVE || $event->current_question_index === null) {
                return $payload;
            }

            $question = $event->questions->firstWhere('position', $event->current_question_index);
            if (! $question || in_array($question->id, $answeredIds, true)) {
                return $payload;
            }

            $openedAt = $event->current_question_opened_at ?? now();
            $secondsLeft = max(0, $event->question_seconds - (int) $openedAt->diffInSeconds(now()));

            $payload['question'] = $question->toPublicArray();
            $payload['seconds_left'] = $secondsLeft;

            return $payload;
        }

        if (! $attempt) {
            return $payload;
        }

        $position = $attempt->current_question_position;
        if ($position === null) {
            return $payload;
        }

        $question = $event->questions->firstWhere('position', $position);
        if (! $question || in_array($question->id, $answeredIds, true)) {
            return $payload;
        }

        if (! $attempt->current_question_shown_at) {
            $attempt->update(['current_question_shown_at' => now()]);
            $attempt->refresh();
        }

        $shownAt = $attempt->current_question_shown_at ?? now();
        $secondsLeft = max(0, $event->question_seconds - (int) $shownAt->diffInSeconds(now()));

        $payload['question'] = $question->toPublicArray();
        $payload['seconds_left'] = $secondsLeft;

        return $payload;
    }

    public function answer(GameEvent $event, User $student, SchoolClass $class, int $questionId, int $selectedIndex): GameEventAttempt
    {
        return DB::transaction(function () use ($event, $student, $class, $questionId, $selectedIndex) {
            $this->tick($event);
            $event = GameEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $event->load('questions');

            $attempt = $this->startOrResumeAttempt($event, $student, $class);
            $attempt = GameEventAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($attempt->isFinished()) {
                throw ValidationException::withMessages([
                    'attempt' => 'Você já concluiu este evento.',
                ]);
            }

            $question = $event->questions->firstWhere('id', $questionId);
            if (! $question) {
                throw ValidationException::withMessages([
                    'question' => 'Pergunta inválida.',
                ]);
            }

            if ($attempt->answers()->where('game_event_question_id', $question->id)->exists()) {
                throw ValidationException::withMessages([
                    'question' => 'Você já respondeu esta pergunta.',
                ]);
            }

            $options = array_values($question->options ?? []);
            if ($selectedIndex < 0 || $selectedIndex >= count($options)) {
                throw ValidationException::withMessages([
                    'selected_index' => 'Alternativa inválida.',
                ]);
            }

            [$openedAt, $allowed] = $this->resolveTiming($event, $attempt, $question);
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'question' => 'O tempo desta pergunta acabou.',
                ]);
            }

            $elapsedMs = (int) max(0, min(
                $event->question_seconds * 1000,
                (int) $openedAt->diffInMilliseconds(now()),
            ));

            $isCorrect = (int) $question->correct_index === $selectedIndex;

            GameEventAnswer::query()->create([
                'game_event_attempt_id' => $attempt->id,
                'game_event_question_id' => $question->id,
                'selected_index' => $selectedIndex,
                'is_correct' => $isCorrect,
                'elapsed_ms' => $elapsedMs,
            ]);

            if ($isCorrect) {
                $attempt->correct_count = (int) $attempt->correct_count + 1;
                $attempt->correct_time_ms = (int) $attempt->correct_time_ms + $elapsedMs;
            }

            $answeredCount = $attempt->answers()->count();
            $total = $event->questions->count();

            if ($event->isWindowMode()) {
                $next = (int) $question->position + 1;
                if ($next < $total) {
                    $attempt->current_question_position = $next;
                    $attempt->current_question_shown_at = now();
                } else {
                    $attempt->finished_at = now();
                    $attempt->current_question_position = null;
                    $attempt->current_question_shown_at = null;
                }
            } elseif ($answeredCount >= $total) {
                $attempt->finished_at = now();
            }

            $attempt->save();

            if ($attempt->isFinished() && ! $attempt->rewards_granted) {
                $this->grantAttemptRewards($event, $attempt, $student, $class);
            }

            return $attempt->fresh(['answers']);
        });
    }

    /**
     * @return Collection<int, GameEventAttempt>
     */
    public function ranking(GameEvent $event): Collection
    {
        return GameEventAttempt::query()
            ->with(['student', 'schoolClass'])
            ->where('game_event_id', $event->id)
            ->whereNotNull('finished_at')
            ->orderByDesc('correct_count')
            ->orderBy('correct_time_ms')
            ->orderBy('finished_at')
            ->orderBy('id')
            ->get();
    }

    public function winner(GameEvent $event): ?GameEventAttempt
    {
        return $this->ranking($event)->first();
    }

    public function scoreForAttempt(GameEvent $event, GameEventAttempt $attempt): float
    {
        $total = max(1, $event->questions()->count());
        $max = (float) ($event->activity?->max_score ?? 100);

        return round(($attempt->correct_count / $total) * $max, 2);
    }

    /**
     * @param  list<array{prompt: string, type: string, options: list<string>, correct_index: int|string}>  $questions
     * @return list<array{prompt: string, type: string, options: list<string>, correct_index: int}>
     */
    public function normalizeQuestions(array $questions): array
    {
        if ($questions === []) {
            throw ValidationException::withMessages([
                'questions' => 'Informe ao menos uma pergunta.',
            ]);
        }

        $normalized = [];

        foreach ($questions as $index => $raw) {
            $prompt = trim((string) ($raw['prompt'] ?? ''));
            $type = (string) ($raw['type'] ?? GameEventQuestion::TYPE_MULTIPLE_CHOICE);
            $options = array_values(array_filter(array_map(
                fn ($option) => trim((string) $option),
                $raw['options'] ?? [],
            ), fn (string $option) => $option !== ''));

            if ($type === GameEventQuestion::TYPE_TRUE_FALSE) {
                $options = ['Verdadeiro', 'Falso'];
            }

            if ($prompt === '') {
                throw ValidationException::withMessages([
                    "questions.$index.prompt" => 'Informe o enunciado.',
                ]);
            }

            if (! in_array($type, [GameEventQuestion::TYPE_MULTIPLE_CHOICE, GameEventQuestion::TYPE_TRUE_FALSE], true)) {
                throw ValidationException::withMessages([
                    "questions.$index.type" => 'Tipo de pergunta inválido.',
                ]);
            }

            if ($type === GameEventQuestion::TYPE_MULTIPLE_CHOICE && (count($options) < 2 || count($options) > 4)) {
                throw ValidationException::withMessages([
                    "questions.$index.options" => 'Use de 2 a 4 alternativas.',
                ]);
            }

            $correctIndex = (int) ($raw['correct_index'] ?? -1);
            if ($correctIndex < 0 || $correctIndex >= count($options)) {
                throw ValidationException::withMessages([
                    "questions.$index.correct_index" => 'Marque a alternativa correta.',
                ]);
            }

            $normalized[] = [
                'prompt' => $prompt,
                'type' => $type,
                'options' => $options,
                'correct_index' => $correctIndex,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array{name: string, slot: string, price?: int, currency?: string, rarity: string, icon: string, css?: ?string, label?: ?string, combat_bonus_percent?: mixed}  $attributes
     */
    public function createPrizeItem(array $attributes, ?SchoolClass $forClass, ?Area $area): ShopItem
    {
        $payload = [
            'name' => $attributes['name'],
            'slot' => $attributes['slot'],
            'price' => (int) ($attributes['price'] ?? 0),
            'currency' => $attributes['currency'] ?? CosmeticCatalog::CURRENCY_RELICS,
            'rarity' => $attributes['rarity'],
            'icon' => $attributes['icon'],
            'css' => $attributes['css'] ?? null,
            'label' => $attributes['label'] ?? null,
            'combat_bonus_percent' => $attributes['combat_bonus_percent'] ?? null,
            'prize_only' => true,
            'stock' => 0,
        ];

        if ($area && ! $forClass) {
            $item = ShopItem::query()->create([
                'class_id' => null,
                'area_id' => $area->id,
                'item_key' => CosmeticCatalog::uniqueKey($payload['slot'], $payload['name']),
                'slot' => $payload['slot'],
                'name' => trim($payload['name']),
                'price' => $payload['price'],
                'currency' => $payload['currency'],
                'rarity' => $payload['rarity'],
                'icon' => $payload['icon'],
                'css' => filled($payload['css'] ?? null) ? $payload['css'] : null,
                'label' => filled($payload['label'] ?? null)
                    ? trim((string) $payload['label'])
                    : ($payload['slot'] === CosmeticCatalog::SLOT_TITLE ? trim($payload['name']) : null),
                'combat_bonus' => CosmeticCatalog::combatBonusFromPercent(
                    $payload['combat_bonus_percent'] ?? null,
                    $payload['rarity'],
                ),
                'prize_only' => true,
            ]);
            CosmeticCatalog::flush();

            return $item;
        }

        return $this->shop->createItem($payload, $forClass);
    }

    private function grantAttemptRewards(GameEvent $event, GameEventAttempt $attempt, User $student, SchoolClass $class): void
    {
        if ($attempt->rewards_granted) {
            return;
        }

        if ($event->awardsCoins()) {
            $correct = (int) $attempt->correct_count;
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($enrollment) {
                if ($event->relics_per_correct > 0) {
                    $enrollment->relics = (int) $enrollment->relics + ($correct * $event->relics_per_correct);
                }
                if ($event->seals_per_correct > 0) {
                    $enrollment->seals = (int) $enrollment->seals + ($correct * $event->seals_per_correct);
                }
                $enrollment->save();
            }

            if ($event->auras_per_correct > 0 && $class->area_id) {
                $area = $class->area ?? Area::query()->find($class->area_id);
                if ($area) {
                    $this->awardAura($area, $student->id, $correct * $event->auras_per_correct);
                }
            }
        }

        if ($event->isActivity() && $event->activity) {
            $score = $this->scoreForAttempt($event, $attempt);
            $this->gameLoop->recordActivityGrade(
                $class,
                $event->activity,
                $score,
                $event->creator ?? User::query()->find($event->created_by) ?? $student,
                $student,
            );
        }

        $attempt->rewards_granted = true;
        $attempt->save();

        $student->notify(new GameAlert(
            'event_result',
            'Resultado do evento',
            $event->title.': '.$attempt->correct_count.' acerto(s).',
            [
                'url' => route('student.events.show', $event),
                'event_id' => $event->id,
            ],
        ));
    }

    private function awardPrize(GameEvent $event): void
    {
        $winner = $this->winner($event);
        if (! $winner || ! $event->prize_item_id) {
            $event->update(['awarded_at' => now()]);

            return;
        }

        $enrollment = Enrollment::query()
            ->where('class_id', $winner->class_id)
            ->where('student_id', $winner->student_id)
            ->lockForUpdate()
            ->first();

        if ($enrollment) {
            EnrollmentCosmetic::query()->firstOrCreate([
                'enrollment_id' => $enrollment->id,
                'item_key' => $event->prizeItem?->item_key ?? ShopItem::query()->find($event->prize_item_id)?->item_key,
            ]);
        }

        $event->update(['awarded_at' => now()]);

        $winner->student?->notify(new GameAlert(
            'event_prize',
            'Você venceu!',
            'Parabéns! Você ganhou o item exclusivo de '.$event->title.'.',
            [
                'url' => route('student.events.show', $event),
                'event_id' => $event->id,
            ],
        ));
    }

    private function awardAura(Area $area, int $studentId, int $amount): void
    {
        $balance = AreaBalance::query()
            ->where('area_id', $area->id)
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = AreaBalance::query()->create([
                'area_id' => $area->id,
                'student_id' => $studentId,
                'auras' => 0,
            ]);
            $balance = AreaBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }

        $balance->auras = (int) $balance->auras + $amount;
        $balance->save();
    }

    /**
     * @return array{0: Carbon, 1: bool}
     */
    private function resolveTiming(GameEvent $event, GameEventAttempt $attempt, GameEventQuestion $question): array
    {
        if ($event->isLiveMode()) {
            if ($event->status !== GameEvent::STATUS_LIVE) {
                return [now(), false];
            }
            if ((int) $event->current_question_index !== (int) $question->position) {
                return [now(), false];
            }
            $openedAt = $event->current_question_opened_at ?? now();
            $allowed = $openedAt->copy()->addSeconds($event->question_seconds)->gte(now());

            return [$openedAt, $allowed];
        }

        if ((int) $attempt->current_question_position !== (int) $question->position) {
            return [now(), false];
        }

        $shownAt = $attempt->current_question_shown_at ?? now();
        $allowed = $shownAt->copy()->addSeconds($event->question_seconds)->gte(now());

        return [$shownAt, $allowed];
    }

    private function assertScope(string $kind, ?SchoolClass $class, ?Area $area): void
    {
        if ($kind === GameEvent::KIND_ACTIVITY || $kind === GameEvent::KIND_CLASS) {
            if (! $class) {
                throw ValidationException::withMessages([
                    'class' => 'Informe a turma do evento.',
                ]);
            }
        }

        if ($kind === GameEvent::KIND_REALM && ! $area) {
            throw ValidationException::withMessages([
                'area' => 'Informe o reino do evento.',
            ]);
        }
    }

    private function assertStudentCanPlay(GameEvent $event, User $student, SchoolClass $class): void
    {
        if (! $student->isStudent()) {
            throw ValidationException::withMessages([
                'student' => 'Apenas alunos participam.',
            ]);
        }

        $enrolled = $student->enrollments()->where('class_id', $class->id)->exists();
        if (! $enrolled) {
            throw ValidationException::withMessages([
                'class' => 'Você não está nesta turma.',
            ]);
        }

        if ($event->isRealmEvent()) {
            if ((int) $class->area_id !== (int) $event->area_id) {
                throw ValidationException::withMessages([
                    'event' => 'Este evento é de outro reino.',
                ]);
            }

            return;
        }

        if ((int) $event->class_id !== (int) $class->id) {
            throw ValidationException::withMessages([
                'event' => 'Este evento é de outra turma.',
            ]);
        }
    }

    private function notifyParticipants(GameEvent $event, string $type, string $title, string $message): void
    {
        $students = collect();

        if ($event->isRealmEvent() && $event->area_id) {
            $classIds = SchoolClass::query()->where('area_id', $event->area_id)->pluck('id');
            $students = User::query()
                ->where('role', 'student')
                ->whereHas('enrollments', fn ($q) => $q->whereIn('class_id', $classIds))
                ->get();
        } elseif ($event->class_id) {
            $students = User::query()
                ->where('role', 'student')
                ->whereHas('enrollments', fn ($q) => $q->where('class_id', $event->class_id))
                ->get();
        }

        foreach ($students as $student) {
            $student->notify(new GameAlert($type, $title, $message, [
                'url' => route('student.events.show', $event),
                'event_id' => $event->id,
            ]));
        }
    }
}
