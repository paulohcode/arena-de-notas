# Arena das Notas

Sistema de notas em formato de game (RPG) para o curso técnico de sistemas. Laravel + MySQL, com ranking de jogadores, Hall das Guildas, XP, níveis e avisos.

## Requisitos

- PHP 8.2+
- Composer
- Node.js 20+
- MySQL / MariaDB

Na hospedagem, o document root deve apontar para `public/`.

## Instalação

```bash
composer install
copy .env.example .env
php artisan key:generate
```

No `.env`, use o MySQL do jogo:

```
APP_NAME="Arena das Notas"
APP_LOCALE=pt_BR
DB_CONNECTION=mysql
DB_HOST=mysql50-farm1.kinghost.net
DB_PORT=3306
DB_DATABASE=versumtech03
DB_USERNAME=versumtech03
DB_PASSWORD=
```

A senha fica só no `.env` (não versionado). Depois rode:

```bash
php artisan migrate
npm install
npm run build
php artisan serve
```

Abra `http://localhost:8000`.

## Acessos iniciais

| Perfil | E-mail | Senha |
|--------|--------|-------|
| Administrador | admin@arena.local | Admin@123 |
| Professor (Sistemas) | admin@escola.local | Professor@123 |
| Alunos demo | ana@escola.local, bruno@escola.local, carla@escola.local, diego@escola.local | aluno123 (troca no primeiro login) |

O administrador cadastra **reinos** (áreas de atuação) e **professores**, vinculando cada professor a um ou mais reinos. A home pública é um **mapa de reinos**; turmas e temporadas ficam isoladas por área.

Novos alunos cadastrados pelo professor também começam com `aluno123`.

## Agendador (obrigatório na hospedagem)

Quizzes ao vivo e a expiração de desafios (24h) usam o comando `php artisan game-events:tick`, disparado pelo agendador do Laravel.

No crontab do servidor (KingHost e equivalentes):

```
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Sem isso, o evento ao vivo não avança de pergunta e os desafios pendentes nunca expiram. A aba Eventos da turma avisa quando o agendador está parado.

## Como usar

1. Professor cria a turma e escolhe se as notas **começam em 0** ou **em 100**.
2. Cadastra alunos, guildas e atividades (individuais ou de equipe).
3. Lança notas 0–100 e ajustes (+30 extra, −20 conduta).
4. No final (ou quando quiser) ajusta os **pesos**. A nota da guilda entra como **uma linha só** na média do aluno (linha “Nota equipe”).
   No **Hall das Guildas**, o placar pondera também a performance individual dos membros: usa a **média individual dos alunos (somente atividades individuais + ajustes)** e combina com a **nota da guilda** (atividades de equipe + ajustes da guilda) para gerar o score final.
5. Aluno entra, vê a ficha, o extrato e decide se aparece no ranking público.

Teto sempre 100. O Hall das Guildas é público mesmo se o aluno ocultar a nota pessoal.
