<?php

use App\Http\Controllers\Admin\AreaController as AdminAreaController;
use App\Http\Controllers\Admin\CurrencyController as AdminCurrencyController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GameEventController as AdminGameEventController;
use App\Http\Controllers\Admin\RealmArenaController as AdminRealmArenaController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\ArenaRulesController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\SeasonRankingController;
use App\Http\Controllers\Student\ArenaController as StudentArenaController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\GameEventController as StudentGameEventController;
use App\Http\Controllers\Student\ShopController as StudentShopController;
use App\Http\Controllers\Teacher\ActivityController;
use App\Http\Controllers\Teacher\ArenaController as TeacherArenaController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\CharacterApprovalController;
use App\Http\Controllers\Teacher\ClassController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\GameEventController as TeacherGameEventController;
use App\Http\Controllers\Teacher\GradeController;
use App\Http\Controllers\Teacher\SeasonController;
use App\Http\Controllers\Teacher\ShopController as TeacherShopController;
use App\Http\Controllers\Teacher\StudentController;
use App\Http\Controllers\Teacher\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RankingController::class, 'home'])->name('home');
Route::get('/regras', [ArenaRulesController::class, 'show'])->name('arena.rules');
Route::get('/reinos/{area:slug}', [AreaController::class, 'show'])->name('areas.show');

Route::get('/ranking/{schoolClass}', [RankingController::class, 'show'])->name('ranking.show');
Route::get('/ranking/{schoolClass}/live', [RankingController::class, 'live'])->name('ranking.live');
Route::get('/ranking/{schoolClass}/guildas/{team}', [RankingController::class, 'guild'])->name('ranking.guild');

Route::get('/temporadas', [SeasonRankingController::class, 'indexRedirect'])->name('seasons.index');
Route::get('/reinos/{area:slug}/temporadas', [SeasonRankingController::class, 'index'])->name('areas.seasons.index');
Route::get('/reinos/{area:slug}/temporadas/{season}', [SeasonRankingController::class, 'show'])->name('areas.seasons.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/senha', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/senha', [PasswordController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'password.changed', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/reinos', [AdminAreaController::class, 'index'])->name('areas.index');
    Route::get('/reinos/novo', [AdminAreaController::class, 'create'])->name('areas.create');
    Route::post('/reinos', [AdminAreaController::class, 'store'])->name('areas.store');
    Route::get('/reinos/{area}/editar', [AdminAreaController::class, 'edit'])->name('areas.edit');
    Route::put('/reinos/{area}', [AdminAreaController::class, 'update'])->name('areas.update');
    Route::patch('/reinos/{area}/posicao', [AdminAreaController::class, 'position'])->name('areas.position');
    Route::delete('/reinos/{area}', [AdminAreaController::class, 'destroy'])->name('areas.destroy');
    Route::get('/reinos/{area}/arena', [AdminRealmArenaController::class, 'show'])->name('areas.arena');
    Route::post('/reinos/{area}/arena/abrir', [AdminRealmArenaController::class, 'open'])->name('areas.arena.open');
    Route::post('/reinos/{area}/arena/fechar', [AdminRealmArenaController::class, 'close'])->name('areas.arena.close');
    Route::put('/reinos/{area}/arena', [AdminRealmArenaController::class, 'update'])->name('areas.arena.update');
    Route::post('/reinos/{area}/arena/duelos/{realmDuel}/cancelar', [AdminRealmArenaController::class, 'cancel'])->name('areas.arena.cancel');

    Route::get('/reinos/{area}/eventos', [AdminGameEventController::class, 'index'])->name('areas.events.index');
    Route::post('/reinos/{area}/eventos', [AdminGameEventController::class, 'store'])->name('areas.events.store');
    Route::get('/reinos/{area}/eventos/{gameEvent}', [AdminGameEventController::class, 'show'])->name('areas.events.show');
    Route::post('/reinos/{area}/eventos/{gameEvent}/iniciar', [AdminGameEventController::class, 'start'])->name('areas.events.start');
    Route::post('/reinos/{area}/eventos/{gameEvent}/encerrar', [AdminGameEventController::class, 'close'])->name('areas.events.close');

    Route::get('/professores', [AdminTeacherController::class, 'index'])->name('teachers.index');
    Route::get('/professores/novo', [AdminTeacherController::class, 'create'])->name('teachers.create');
    Route::post('/professores', [AdminTeacherController::class, 'store'])->name('teachers.store');
    Route::get('/professores/{teacher}/editar', [AdminTeacherController::class, 'edit'])->name('teachers.edit');
    Route::put('/professores/{teacher}', [AdminTeacherController::class, 'update'])->name('teachers.update');
    Route::delete('/professores/{teacher}', [AdminTeacherController::class, 'destroy'])->name('teachers.destroy');

    Route::get('/loja', [AdminShopController::class, 'index'])->name('shop.index');
    Route::post('/loja/itens', [AdminShopController::class, 'store'])->name('shop.items.store');
    Route::get('/loja/itens/{shopItem}/editar', [AdminShopController::class, 'edit'])->name('shop.items.edit');
    Route::put('/loja/itens/{shopItem}', [AdminShopController::class, 'update'])->name('shop.items.update');

    Route::get('/moedas', [AdminCurrencyController::class, 'index'])->name('currencies.index');
    Route::put('/moedas', [AdminCurrencyController::class, 'update'])->name('currencies.update');
});

Route::middleware(['auth', 'password.changed', 'role:teacher'])->prefix('professor')->name('teacher.')->group(function () {
    Route::get('/', [TeacherDashboardController::class, 'index'])->name('dashboard');
    Route::get('/turmas/nova', [ClassController::class, 'create'])->name('classes.create');
    Route::post('/turmas', [ClassController::class, 'store'])->name('classes.store');
    Route::get('/turmas/{schoolClass}', [TeacherDashboardController::class, 'show'])->name('classes.show');
    Route::get('/turmas/{schoolClass}/editar', [ClassController::class, 'edit'])->name('classes.edit');
    Route::put('/turmas/{schoolClass}', [ClassController::class, 'update'])->name('classes.update');
    Route::delete('/turmas/{schoolClass}', [ClassController::class, 'destroy'])->name('classes.destroy');

    Route::get('/turmas/{schoolClass}/alunos/acessos.pdf', [StudentController::class, 'export'])->name('students.export');
    Route::get('/turmas/{schoolClass}/alunos/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::put('/turmas/{schoolClass}/alunos/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('/turmas/{schoolClass}/alunos', [StudentController::class, 'store'])->name('students.store');
    Route::post('/turmas/{schoolClass}/alunos/vincular', [StudentController::class, 'attach'])->name('students.attach');
    Route::post('/turmas/{schoolClass}/alunos/{student}/transferir', [StudentController::class, 'transfer'])->name('students.transfer');
    Route::post('/turmas/{schoolClass}/alunos/{student}/senha', [StudentController::class, 'resetPassword'])->name('students.password');
    Route::delete('/turmas/{schoolClass}/alunos/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::put('/turmas/{schoolClass}/alunos/{student}/personagem', [CharacterApprovalController::class, 'update'])->name('characters.update');
    Route::post('/turmas/{schoolClass}/alunos/{student}/personagem/aprovar', [CharacterApprovalController::class, 'approve'])->name('characters.approve');
    Route::post('/turmas/{schoolClass}/personagens/aprovar', [CharacterApprovalController::class, 'approveAll'])->name('characters.approve-all');
    Route::post('/turmas/{schoolClass}/alunos/{student}/personagem/rejeitar', [CharacterApprovalController::class, 'reject'])->name('characters.reject');

    Route::post('/turmas/{schoolClass}/guildas', [TeamController::class, 'store'])->name('teams.store');
    Route::put('/turmas/{schoolClass}/guildas/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/turmas/{schoolClass}/guildas/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    Route::post('/turmas/{schoolClass}/atividades', [ActivityController::class, 'store'])->name('activities.store');
    Route::put('/turmas/{schoolClass}/atividades/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('/turmas/{schoolClass}/atividades/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    Route::post('/turmas/{schoolClass}/atividades/{activity}/avisar', [ActivityController::class, 'warnMissing'])->name('activities.warn');
    Route::put('/turmas/{schoolClass}/pesos', [ActivityController::class, 'weights'])->name('activities.weights');
    Route::post('/turmas/{schoolClass}/atividades-evento', [TeacherGameEventController::class, 'storeActivity'])->name('activities.event.store');

    Route::post('/turmas/{schoolClass}/eventos', [TeacherGameEventController::class, 'store'])->name('events.store');
    Route::post('/turmas/{schoolClass}/eventos-reino', [TeacherGameEventController::class, 'storeRealm'])->name('events.realm.store');
    Route::get('/turmas/{schoolClass}/eventos/{gameEvent}', [TeacherGameEventController::class, 'show'])->name('events.show');
    Route::post('/turmas/{schoolClass}/eventos/{gameEvent}/iniciar', [TeacherGameEventController::class, 'start'])->name('events.start');
    Route::post('/turmas/{schoolClass}/eventos/{gameEvent}/encerrar', [TeacherGameEventController::class, 'close'])->name('events.close');

    Route::post('/turmas/{schoolClass}/notas', [GradeController::class, 'store'])->name('grades.store');
    Route::post('/turmas/{schoolClass}/ajustes', [GradeController::class, 'adjust'])->name('grades.adjust');
    Route::post('/turmas/{schoolClass}/alunos/{student}/comportamento', [GradeController::class, 'behavior'])->name('grades.behavior');

    Route::post('/turmas/{schoolClass}/chamadas', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('/turmas/{schoolClass}/chamadas/{attendanceSession}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::delete('/turmas/{schoolClass}/chamadas/{attendanceSession}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

    Route::post('/turmas/{schoolClass}/arena/abrir', [TeacherArenaController::class, 'open'])->name('arena.open');
    Route::post('/turmas/{schoolClass}/arena/fechar', [TeacherArenaController::class, 'close'])->name('arena.close');
    Route::put('/turmas/{schoolClass}/arena', [TeacherArenaController::class, 'update'])->name('arena.update');
    Route::put('/turmas/{schoolClass}/arena/reino', [TeacherArenaController::class, 'updateRealm'])->name('arena.realm.update');
    Route::post('/turmas/{schoolClass}/arena/reino/{realmDuel}/cancelar', [TeacherArenaController::class, 'cancelRealm'])->name('arena.realm.cancel');

    Route::get('/turmas/{schoolClass}/loja', [TeacherShopController::class, 'show'])->name('shop.show');
    Route::post('/turmas/{schoolClass}/loja/itens', [TeacherShopController::class, 'store'])->name('shop.items.store');
    Route::get('/turmas/{schoolClass}/loja/itens/{shopItem}/editar', [TeacherShopController::class, 'edit'])->name('shop.items.edit')->scopeBindings();
    Route::put('/turmas/{schoolClass}/loja/itens/{shopItem}', [TeacherShopController::class, 'update'])->name('shop.items.update')->scopeBindings();
    Route::post('/turmas/{schoolClass}/loja/estoque', [TeacherShopController::class, 'restock'])->name('shop.restock');

    Route::get('/temporadas', [SeasonController::class, 'index'])->name('seasons.index');
    Route::get('/temporadas/nova', [SeasonController::class, 'create'])->name('seasons.create');
    Route::post('/temporadas', [SeasonController::class, 'store'])->name('seasons.store');
    Route::get('/temporadas/{season}/editar', [SeasonController::class, 'edit'])->name('seasons.edit');
    Route::put('/temporadas/{season}', [SeasonController::class, 'update'])->name('seasons.update');
    Route::delete('/temporadas/{season}', [SeasonController::class, 'destroy'])->name('seasons.destroy');
});

Route::middleware(['auth', 'password.changed', 'role:student'])->prefix('aluno')->name('student.')->group(function () {
    Route::get('/classe', [StudentDashboardController::class, 'editCharacter'])->name('character.edit');
    Route::post('/classe', [StudentDashboardController::class, 'updateCharacter'])->name('character.update');

    Route::middleware('character.class')->group(function () {
        Route::get('/', [StudentDashboardController::class, 'index'])->name('dashboard');
        Route::post('/turma', [StudentDashboardController::class, 'switchClass'])->name('class.switch');
        Route::post('/privacidade', [StudentDashboardController::class, 'privacy'])->name('privacy');
        Route::get('/avisos', [StudentDashboardController::class, 'notifications'])->name('notifications');
        Route::post('/avisos/ler', [StudentDashboardController::class, 'markRead'])->name('notifications.read');

        Route::get('/arena', [StudentArenaController::class, 'index'])->name('arena.index');
        Route::get('/arena/desafios-pendentes', [StudentArenaController::class, 'pending'])->name('arena.pending');
        Route::post('/arena/desafiar', [StudentArenaController::class, 'challenge'])->name('arena.challenge');
        Route::get('/arena/duelos/{duel}', [StudentArenaController::class, 'show'])->name('arena.show');
        Route::get('/arena/duelos/{duel}/status', [StudentArenaController::class, 'status'])->name('arena.status');
        Route::post('/arena/duelos/{duel}/aceitar', [StudentArenaController::class, 'accept'])->name('arena.accept');
        Route::post('/arena/duelos/{duel}/recusar', [StudentArenaController::class, 'decline'])->name('arena.decline');

        Route::post('/arena/guildas/desafiar', [StudentArenaController::class, 'challengeGuild'])->name('arena.guild.challenge');
        Route::get('/arena/guildas/{teamBattle}', [StudentArenaController::class, 'showGuild'])->name('arena.guild.show');
        Route::get('/arena/guildas/{teamBattle}/status', [StudentArenaController::class, 'statusGuild'])->name('arena.guild.status');
        Route::post('/arena/guildas/{teamBattle}/aceitar', [StudentArenaController::class, 'acceptGuild'])->name('arena.guild.accept');
        Route::post('/arena/guildas/{teamBattle}/recusar', [StudentArenaController::class, 'declineGuild'])->name('arena.guild.decline');

        Route::get('/arena/reino', [StudentArenaController::class, 'realmIndex'])->name('arena.realm.index');
        Route::post('/arena/reino/desafiar', [StudentArenaController::class, 'challengeRealm'])->name('arena.realm.challenge');
        Route::get('/arena/reino/{realmDuel}', [StudentArenaController::class, 'showRealm'])->name('arena.realm.show');
        Route::get('/arena/reino/{realmDuel}/status', [StudentArenaController::class, 'statusRealm'])->name('arena.realm.status');
        Route::post('/arena/reino/{realmDuel}/aceitar', [StudentArenaController::class, 'acceptRealm'])->name('arena.realm.accept');
        Route::post('/arena/reino/{realmDuel}/recusar', [StudentArenaController::class, 'declineRealm'])->name('arena.realm.decline');

        Route::get('/loja', [StudentShopController::class, 'index'])->name('shop.index');
        Route::post('/loja/comprar', [StudentShopController::class, 'purchase'])->name('shop.purchase');
        Route::post('/loja/anunciar', [StudentShopController::class, 'list'])->name('shop.list');
        Route::post('/loja/anunciar/cancelar', [StudentShopController::class, 'unlist'])->name('shop.unlist');
        Route::post('/loja/anuncios/{listing}/comprar', [StudentShopController::class, 'buyListing'])->name('shop.listings.buy');
        Route::post('/loja/equipar', [StudentShopController::class, 'equip'])->name('shop.equip');
        Route::post('/loja/desequipar', [StudentShopController::class, 'unequip'])->name('shop.unequip');

        Route::get('/eventos', [StudentGameEventController::class, 'index'])->name('events.index');
        Route::get('/eventos/{gameEvent}', [StudentGameEventController::class, 'show'])->name('events.show');
        Route::post('/eventos/{gameEvent}/entrar', [StudentGameEventController::class, 'join'])->name('events.join');
        Route::get('/eventos/{gameEvent}/estado', [StudentGameEventController::class, 'state'])->name('events.state');
        Route::post('/eventos/{gameEvent}/responder', [StudentGameEventController::class, 'answer'])->name('events.answer');
    });
});
