<?php

use App\Http\Controllers\Admin\AreaController as AdminAreaController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\SeasonRankingController;
use App\Http\Controllers\Student\ArenaController as StudentArenaController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\ShopController as StudentShopController;
use App\Http\Controllers\Teacher\ActivityController;
use App\Http\Controllers\Teacher\ArenaController as TeacherArenaController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\CharacterApprovalController;
use App\Http\Controllers\Teacher\ClassController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\Teacher\GradeController;
use App\Http\Controllers\Teacher\SeasonController;
use App\Http\Controllers\Teacher\ShopController as TeacherShopController;
use App\Http\Controllers\Teacher\StudentController;
use App\Http\Controllers\Teacher\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RankingController::class, 'home'])->name('home');
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

    Route::get('/professores', [AdminTeacherController::class, 'index'])->name('teachers.index');
    Route::get('/professores/novo', [AdminTeacherController::class, 'create'])->name('teachers.create');
    Route::post('/professores', [AdminTeacherController::class, 'store'])->name('teachers.store');
    Route::get('/professores/{teacher}/editar', [AdminTeacherController::class, 'edit'])->name('teachers.edit');
    Route::put('/professores/{teacher}', [AdminTeacherController::class, 'update'])->name('teachers.update');
    Route::delete('/professores/{teacher}', [AdminTeacherController::class, 'destroy'])->name('teachers.destroy');

    Route::get('/loja', [AdminShopController::class, 'index'])->name('shop.index');
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
    Route::post('/turmas/{schoolClass}/alunos/{student}/personagem/rejeitar', [CharacterApprovalController::class, 'reject'])->name('characters.reject');

    Route::post('/turmas/{schoolClass}/guildas', [TeamController::class, 'store'])->name('teams.store');
    Route::put('/turmas/{schoolClass}/guildas/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('/turmas/{schoolClass}/guildas/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    Route::post('/turmas/{schoolClass}/atividades', [ActivityController::class, 'store'])->name('activities.store');
    Route::put('/turmas/{schoolClass}/atividades/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('/turmas/{schoolClass}/atividades/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    Route::post('/turmas/{schoolClass}/atividades/{activity}/avisar', [ActivityController::class, 'warnMissing'])->name('activities.warn');
    Route::put('/turmas/{schoolClass}/pesos', [ActivityController::class, 'weights'])->name('activities.weights');

    Route::post('/turmas/{schoolClass}/notas', [GradeController::class, 'store'])->name('grades.store');
    Route::post('/turmas/{schoolClass}/ajustes', [GradeController::class, 'adjust'])->name('grades.adjust');
    Route::post('/turmas/{schoolClass}/alunos/{student}/comportamento', [GradeController::class, 'behavior'])->name('grades.behavior');

    Route::post('/turmas/{schoolClass}/chamadas', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::put('/turmas/{schoolClass}/chamadas/{attendanceSession}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::delete('/turmas/{schoolClass}/chamadas/{attendanceSession}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

    Route::post('/turmas/{schoolClass}/arena/abrir', [TeacherArenaController::class, 'open'])->name('arena.open');
    Route::post('/turmas/{schoolClass}/arena/fechar', [TeacherArenaController::class, 'close'])->name('arena.close');
    Route::put('/turmas/{schoolClass}/arena', [TeacherArenaController::class, 'update'])->name('arena.update');

    Route::get('/turmas/{schoolClass}/loja', [TeacherShopController::class, 'show'])->name('shop.show');
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

        Route::get('/loja', [StudentShopController::class, 'index'])->name('shop.index');
        Route::post('/loja/comprar', [StudentShopController::class, 'purchase'])->name('shop.purchase');
        Route::post('/loja/anunciar', [StudentShopController::class, 'list'])->name('shop.list');
        Route::post('/loja/anunciar/cancelar', [StudentShopController::class, 'unlist'])->name('shop.unlist');
        Route::post('/loja/anuncios/{listing}/comprar', [StudentShopController::class, 'buyListing'])->name('shop.listings.buy');
        Route::post('/loja/equipar', [StudentShopController::class, 'equip'])->name('shop.equip');
        Route::post('/loja/desequipar', [StudentShopController::class, 'unequip'])->name('shop.unequip');
    });
});
