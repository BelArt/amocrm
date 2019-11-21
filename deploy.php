<?php

namespace Deployer;

// это на удалённом сервере
define('REMOTE_PATH', "/var/www/mmco-expo.ru");
// это на локальном
define('LOCAL_PATH', __DIR__);

// https://github.com/deployphp/deployer/issues/980
set('ssh_type', 'native');
set('ssh_multiplexing', true);
set('use_ssh2', false);

server('mmco-expo', '79.143.30.36')
    ->user('mmcoexpo')
    ->forwardAgent()
    ->set('deploy_path', REMOTE_PATH);

set('mmcoexpo', REMOTE_PATH . "/mmcoexpo");
set('user', REMOTE_PATH . "/user");
set('release', REMOTE_PATH . "/release");
set('shared', REMOTE_PATH . "/shared");
set('mmcoexpo_symlink', REMOTE_PATH . "/release/_mmcoexpo");

// сами папки для пользователей будет формироваться на лету исходя из входных данных

set('rep_mmcoexpo', "git@github.com:BelArt/amocrm.git");
set('rep_user', "git@github.com:BelArt");

set('mmcoexpo_new', false); // если деплоем mmcoexpo, то будет true
set('remove_git', true);
set('keep_releases', 6);
set('env_file', LOCAL_PATH . '/.env'); // название файла окружения
set('env_test', 'test'); // название тестового окружения

set('deploy_type', null); // Тип деплоя (_mmcoexpo или имя пользователя)
set(
    'deploy_end',
    false
); // Окончился ли деплой или нет (при деплои ядра и пользователя, только после пользователя завершается)
set('deploy_mmcoexpo_rollback', false); // Запуск отката mmcoexpo в случае ошибки
set(
    'deploy_user_rollback',
    false
); // Запуск отката пользователя в случае ошибки, внутри будет указанны данные для отката

set('shared_dirs', ['env', 'data', 'log', 'daemon']); // можно шарить файлы и папки из amocrm-директории
set('writable_dirs', ['temp', 'daemon', 'log']); // можно делать записываемыми папки из amocrm-директории
set('shared_files', []);
set('writable_use_sudo', false); // Using sudo in writable commands?
set('http_user', null);

// указываем каких пользователей нужно деплоить
option('user', 'u', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Set user(s) for deploy');
// указываем релизы, которые нужно откатить при rollback
option('release', 'r', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Set number or a release');
// указываем, что если тестов у пользователя нет, то он автоматом допускается к деплою, ну или просто "типа" его тесты пройдены успешно
option('pass', 's', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Force not-exist tests of user');
// можно указать какие конкретно демоны должны быть остановленны во время деплоя
// в этом случае другие не будут тронуты не смотря ни на что,
// указывать необходимо название самих .pid-файлов без расширения.
// Именование домнов по следующему приципу:
// <env>-user-<username>-<controller>_<action>
// prod-user-moevideocrm-onlinepbx_run
option('daemon', 'd', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Set daemon(s) for stoping');
// с помощью этого можно пропускать сборку крон-задач, чтобы при деплое только один раз запускать сборку
option('cron', 'o', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Force compile cron-tasks');
// с помощью этого можно пропускать сборку крон-задач, чтобы при деплое только один раз запускать сборку
option('user_branch', 'b', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'User branch for deploy');
// Конмада для исполнения на удалённом сервере
option('command', 'e', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Command for execution');

/**
 * определяем место последнего релиза mmcoexpo, чтобы знать в случае смены релиза, где исходный был
 * это необходим для остановки демонов т.к. pid-файлы лежат в temp-директории (старого) релиза
 */
set(
    'mmcoexpo_current',
    function () {
        // проверим существование симлинка
        if (run('[[ -L {{mmcoexpo}} && -d {{mmcoexpo}} ]] && printf "1" || printf "0"')->toString()) {
            return run("readlink -n {{mmcoexpo}}")->toString();
        } else {
            return false;
        }
    }
);

/**
 * Название текущего релиза, чтобы везде он был доступен
 */
set(
    'mmcoexpo_release',
    function () {
        if (get('deploy_type') == '_mmcoexpo') {
            return date('Y_m_d_His');
        } else {
            return get('mmcoexpo');
        }
    }
);

/**
 * Preparing server for deployment.
 */
task(
    'deploy:prepare',
    function () {
        run("mkdir -p {{mmcoexpo}}");
        run("mkdir -p {{user}}");
        run("mkdir -p {{mmcoexpo_symlink}}");
        run("mkdir -p {{shared}}");
    }
)->desc('Preparing server for deploy');

/**
 * Исполняет команду на удалённом сервере
 */
task(
    'command:exec',
    function () {
        $command = input()->getOption('command', null);

        if ($command) {
            writeln(run('cd ' . getMmcoexpoCurrent() . ' && ' . $command)->toString());
        } else {
            note('Команда не указана');
        }
    }
)->desc('Исполняет команду на удалённом сервере');

/**
 * деплой самого фреймворка
 */
task(
    'deploy:mmcoexpo',
    function () {
        set('deploy_type', '_mmcoexpo');

        $pass = input()->getOption('pass', null);
        $user = input()->getOption('user', null);

        // деплоим сам фреймворк
        $release = get('mmcoexpo_symlink') . '/' . get('mmcoexpo_release');

        note('Stared deploy framework mmcoexpo to ' . $release);

        try {
            // клонируем master
            run("git clone -b master --depth 1 --recursive -q {{rep_mmcoexpo}} {$release}");
            // 		run("chmod -R g+rw {$release}");
            note('Git repository mmcoexpo was cloned to ' . $release);

            // удаляем репозиторий git
            if (get('remove_git')) {
                run("rm -rf {$release}/.git");
                run("rm -f {$release}/.gitignore");
                note('Git-files removed');
            }

            // удаляем папку для пользователей, чтобы заменить на симлинк
            run("rm -rf {$release}/app/user");
            // симлинк на пользователей
            run("ln -s {{user}} {$release}/app/user");
            note('Created symlink for users folder');

            // конфиг TODO: можно распарсить и спросить про каждый параметр у пользователя, а потом результата записать в новый файл
            run(
                "cp {$release}/app/resource/config/parameter_prod.php.sample {$release}/app/resource/config/parameter_prod.php"
            );
            note('Created parameters');

            // скачиваем composer
            run("cd {$release} && curl -sS https://getcomposer.org/installer | php");
            // обновляем вендоры
            run(
                "cd {$release} && php composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-progress --no-scripts"
            );
            note('Installed composer dependency');

            // генерация asset
            run("cd {$release} && php console asset:dump");
            note('Generated assets');

            // TODO прогрев кеша, хотя пока вроде не нужен

            // переключаем продакшен на новый релиз
            /*
                    run("rm -rf {{mmcoexpo}}");
                    run("ln -sfn {$release} {{mmcoexpo}}");
                    note('Switch mmcoexpo to the new release');
            */

            // запустим деплой пользователей если они были указаны
            if (!$user) {
                set('deploy_end', true);
            }
        } catch (\RuntimeException $e) {
            error('Exception: ' . $e->getMessage());
            set('deploy_mmcoexpo_rollback', get('mmcoexpo_release'));

            return true;
        }
    }
)->desc('Deploy only mmcoexpo or mmcoexpo & users.');

/**
 * Переключение mmcoexpo на новый релиз (создание симлинка)
 */
task(
    'switch:mmcoexpo',
    function () {
        $release = get('mmcoexpo_symlink') . '/' . get('mmcoexpo_release');

        // проверим существование теукщего релиза, а то мог быть rollback
        if (!(run('[ -d "' . $release . '" ] && printf "1" || printf "0"')->toString())) {
            note('Release ' . get('mmcoexpo_release') . ' do not exist');

            return true;
        }

        run("rm -rf {{mmcoexpo}}");
        run("ln -sfn {$release} {{mmcoexpo}}");
        note('Switched mmcoexpo to the new release');
    }
)->desc('Switch mmcoexpo to the new release.');

task(
    'deploy:user',
    function () {
        $users      = input()->getOption('user', null);
        $users      = array_filter(explode(',', $users));
        $userBranch = input()->getOption('user_branch', null);

        if (empty($users)) {
            note('Deploy of users do not need');

            return true;
        }

        // проверим существование каждого пользователя
        foreach ($users as $user) {
            note("Check repository of {$user}");
            run(
                "git ls-remote {{rep_user}}/{$user}.git"
            ); // если репозитория не будет найдено, то выкенет RuntimeException
        }
        note('Checked existence repositories of a user(s)');

        $now = date('Y_m_d_His');

        // зальём все необходимые репозитории пользователей
        foreach ($users as $user) {
            note('Stared deploy user "' . $user . '"');

            // можно сделать деплой обычный, когда пользователь заливается
            // а можно сделать симлинк на уже существующего пользователя
            // чтобы в один интерфейс могли доступаться разные пользователи (с ACL, например)
            // пользователь как симлинк создаётся user2>user1 при этом user1 уже должен существовать
            $sym = explode('>', $user);
            if (count($sym) > 1) {
                try {
                    if (!userExists($sym[1])) {
                        throw new \RuntimeException('Symlink ' . $user . ' not created');
                    }

                    run("ln -s {{user}}/{$sym[1]}/ {{user}}/{$sym[0]}");
                    note('Created symlink "' . $sym[0] . '" > "' . $sym[1] . '" for virtual user');
                } catch (\RuntimeException $e) {
                    warning($e->getMessage());
                }
                continue;
            }

            try {
                $release = "{{release}}/{$user}/{$now}";
                $current = "{{user}}/{$user}";
                //$symlink = "{$env['user_symlink']}/{$user}";

                // создали папки для пользователя если их нет
                run("mkdir -p {$release}");
                //run("mkdir -p {$current}");
                // клонируем
                run(
                    "git clone -b " . ($userBranch ? : 'master')
                    . " --depth 1 --recursive -q {{rep_user}}/{$user}.git {$release}"
                );
                // 			run("chmod -R g+rw $release");
                note('Git repository user "' . $user . '" was cloned to ' . $release);

                // удаляем репозиторий git
                if (get('remove_git')) {
                    run("rm -rf {$release}/.git");
                    run("rm -f {$release}/.gitignore");
                    note('Git-files removed');
                }

                // если у пользователя есть зависимости, то подтягиваем их
                if (run('[ -f "' . $release . '/composer.json" ] && printf "1" || printf "0"')->toString()) {
                    // скачиваем composer
                    run("cd {$release} && curl -sS https://getcomposer.org/installer | php");
                    // обновляем вендоры
                    run(
                        "cd {$release} && php composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-progress --no-scripts"
                    );
                    note('Installed composer dependency of a user');
                }

                // создаём симлинк текущего пользователя на последнюю реализацию
                run("rm -rf {$current}");
                note("ln -sfn {$release} {$current}");
                run("ln -sfn {$release} {$current}");
                note('Switch user "' . $user . '" to the new release');

                // создаём симлинк на текущего пользователя для mmcoexpo
                //run("rm -rf {$symlink}");
                //run("ln -sfn {$current} {$symlink}");

                set('deploy_type', $user);
            } catch (\RuntimeException $e) {
                error('Exception: ' . $e->getMessage());
                set(
                    'deploy_user_rollback',
                    [
                        'user'    => $user,
                        'release' => $now,
                    ]
                );

                return true;
            }
        }

        set('deploy_end', true);
    }
)->desc('Deploy users.');

task(
    'remove:user',
    function () {
        $users = input()->getOption('user', null);
        $users = array_filter(explode(',', $users));

        if (empty($users)) {
            note('Remove of users do not need');

            return true;
        }

        try {
            // Удаляем cron-задачи
            $paths = getCronPaths($users);
            $tasks = getCronTasks(array_column($paths, 'name'));

            $tasks = trim(implode("\n", $tasks), "\n");

            if ($tasks) {
                run('cat <(echo "' . $tasks . '") | crontab');
            } else {
                run('cat <(echo "") | crontab');
            }

            note('Removed cron-tasks');

            foreach ($users as $user) {
                note('Stared removing user "' . $user . '"');
                $releases = "{{release}}/{$user}";
                $current  = "{{user}}/{$user}";

                run("rm -rf {$current}");
                run("rm -rf {$releases}");

                note('Remoed releases of user "' . $user . '"');
            }
        } catch (\RuntimeException $e) {
            error('Exception: ' . $e->getMessage());

            return true;
        }

        set('deploy_end', true);
    }
)->desc('Remove users.');

/**
 * Cleanup old releases of mmcoexpo
 */
task(
    'mmcoexpo:cleanup',
    function () {
        note('Started cleaning mmcoexpo');

        $releases = getMmcoexpoReleases();

        $keep = get('keep_releases');
        while ($keep > 0) {
            array_shift($releases);
            --$keep;
        }

        foreach ($releases as $release) {
            run("rm -rf {{mmcoexpo_symlink}}/$release");
        }

        if ($count = count($releases)) {
            note('Completed cleaning mmcoexpo: removed ' . $count . ' releases');
        } else {
            note('Nothing removed');
        }
    }
)->desc('Cleaning up old releases of an mmcoexpo');

/**
 * Cleanup old releases of user(s)
 */
task(
    'user:cleanup',
    function () {
        $users = input()->getOption('user', null);
        $users = array_filter(explode(',', $users));

        if (empty($users)) {
            note('Cleanup of users do not need');

            return true;
        }

        foreach ($users as $user) {
            note('Started cleaning user "' . $user . '"');

            // проверим есть ли задеплоеная директория данного пользователя (он может быть только в разработке)
            if (!userExists($user)) {
                continue;
            }

            $releases = getUserReleases($user);

            $keep = get('keep_releases');
            while ($keep > 0) {
                array_shift($releases);
                --$keep;
            }

            foreach ($releases as $release) {
                run("rm -rf {{release}}/{$user}/$release");
            }

            if ($count = count($releases)) {
                note('Completed cleaning user "' . $user . '": removed ' . $count . ' releases');
            } else {
                note('Nothing removed');
            }
        }
    }
)->desc('Cleaning up old releases of an user(s)');

/**
 * Delete new release of mmcoexpo if something goes wrong
 */
task(
    'mmcoexpo:rollback_fail',
    function () {
        $release = get('deploy_mmcoexpo_rollback');

        if (!$release) {
            return false;
        }

        run("rm -rf {{mmcoexpo_symlink}}/$release");
    }
)->desc("Rollback of mmcoexpo to previous version.");

/**
 * Delete new release of user if something goes wrong
 */
task(
    'user:rollback_fail',
    function () {
        $data = get('deploy_user_rollback');

        $release = $data['release'];

        // Если нет release, то сейчас не надо откатываться
        if (!$release) {
            return false;
        }

        $user = $data['user'];

        if (!$user) {
            throw new \RuntimeException("Who to delete?");
        }

        run("rm -rf {{release}}/$user/$release");
    }
)->desc("Rollback of mmcoexpo to previous version.");

/**
 * Rollback to previous release and delete current release of mmcoexpo
 */
task(
    'mmcoexpo:rollback',
    function () {
        note('Started rollback mmcoexpo');

        $current  = getMmcoexpoCurrent();
        $releases = getMmcoexpoReleases();

        if (isset($releases[1])) {
            // переключаем продашен на новый релиз
            run("rm -f {{mmcoexpo}}");
            run("ln -sfn {{mmcoexpo_symlink}}/{$releases[1]} {{mmcoexpo}}");

            run("rm -rf $current");

            note('Rollbacked mmcoexpo to ' . $releases[1]);
        } else {
            warning("No more releases you can revert to");
        }
    }
)->desc("Rollback of mmcoexpo to previous version.");

/**
 * Rollback to previous release and delete current release of user
 */
task(
    'user:rollback',
    function () {
        $users = input()->getOption('user', null);
        $users = array_filter(explode(',', $users));

        if (empty($users)) {
            note('Rollback of users do not need');

            return true;
        }

        foreach ($users as $user) {
            note('Started rollback user "' . $user . '"');

            // проверим есть ли задеплоеная директория данного пользователя (он может быть только в разработке)
            if (!userExists($user)) {
                warning('User "' . $user . '" have not releases');
                continue;
            }

            $current  = getUserCurrent($user);
            $releases = getUserReleases($user);

            if (isset($releases[1])) {
                // переключаем продашен на новый релиз
                run("rm -f {{user}}/{$user}");
                run("ln -sfn {{release}}/{$user}/{$releases[1]} {{user}}/{$user}");

                run("rm -rf $current");

                note('Rollbacked user "' . $user . '" to ' . $releases[1]);
            } else {
                warning('No more releases for "' . $user . '" you can revert to');
            }
        }
    }
)->desc("Rollback of mmcoexpo to previous version.");

/**
 * Тестирование самого mmcoexpo
 */
task(
    'test:mmcoexpo',
    function () {
        note('Started test mmcoexpo');

        $pass = input()->getOption('pass');
        $user = input()->getOption('user', null);

        if ($pass) {
            note('Skiped testing mmcoexpo');

            return true;
        }

        $oldEnv = setEnv(get('env_file'), get('env_test'));

        try {
            $output = runLocally('php codecept.phar run', null)->toString();
        } catch (Exception $e) {
            $output = 'Error: ' . $e->getMessage();
        } finally {
            setEnv(get('env_file'), $oldEnv);
        }

        if (strripos($output, 'fail') || strripos($output, 'error')) {
            throw new \RuntimeException(
                'Tests failed!' . "\n\n" . $output . "\n\n" . 'For more info run "php codecept.phar run"'
            );
        }

        note('<info>Tests completed successfully</info>');
    }
)->desc("Testing of an mmcoexpo.");

/**
 * Тестирование пользователей отдельно
 */
task(
    'test:user',
    function () {
        $pass  = input()->getOption('pass');
        $users = input()->getOption('user', null);
        $users = array_filter(explode(',', $users));

        if ($pass) {
            note('Tests of users do pass');

            return;
        }

        if (empty($users)) {
            note('Test of users do not need');

            return true;
        }

        foreach ($users as $user) {
            note('Started test user "' . $user . '"');

            $path = LOCAL_PATH . '/app/user/' . $user;
            // проверим существует ли пользователь
            if (!file_exists($path)) {
                if (!$pass) {
                    throw new \RuntimeException('User ' . $user . ' is not exists');
                } else {
                    note('Skiped testing user "' . $user . '" cause user not exists');
                    continue;
                }
            }

            // проверим существуют ли тесты у пользователя
            if (!file_exists($path . '/' . 'codeception.yml') || !file_exists($path . '/' . 'tests')) {
                if (!$pass) {
                    throw new \RuntimeException('Tests of ' . $user . ' failed');
                } else {
                    note('Skiped testing user "' . $user . '" cause user have not tests');
                    continue;
                }
            }

            $oldEnv = setEnv(get('env_file'), get('env_test'));

            try {
                $output = runLocally('php codecept.phar run -c ' . $path, null)->toString();
            } catch (Exception $e) {
                $output = 'Error: ' . $e->getMessage();
            } finally {
                setEnv(get('env_file'), $oldEnv);
            }

            if (strripos($output, 'fail') !== false || strripos($output, 'error') !== false) {
                throw new \RuntimeException(
                    'Tests of ' . $widget . ' failed!' . "\n\n" . $output . "\n\n"
                    . 'For more info run "php codecept.phar run -c app/user/' . $widget . '"'
                );
            }

            note('<info>Tests of user "' . $user . '" completed successfully</info>');
        }
    }
)->desc("Testing of a users.");

/**
 * Останавливаем залоченные ранее cron-задачи с помощью kill или kill -9,
 * но только, если она принадлежит определённым пользователям.
 */
task(
    'daemons:terminate',
    function () {
        $deployEnd = get('deploy_end');

        if (!$deployEnd) {
            return true;
        }

        note('Started killing the daemons }:->');

        // может быть предыдущего релиза не быть, если первый раз деплоим
        if (!($path = get('mmcoexpo_current'))) {
            note('The previous release was not }:->');

            return true;
        }

        $path .= '/daemon/';

        // если папка с демонами не существует, то всё заканчиваем
        if (!(run('[ -d "' . $path . '" ] && printf "1" || printf "0"')->toString())) {
            note('Daemons do not exist }:->');

            return true;
        }

        // получаем все .pid-файлы, чтобы позже зафильтровать их
        $files = explode(PHP_EOL, run("ls {$path}"));
        rsort($files);
        $files = array_filter(
            $files,
            function ($file) {
                $file = trim($file);

                return !empty($file);
            }
        );

        if (empty($files)) {
            note('Daemons do not exist at all }:->');

            return true;
        }

        $filesSource = $files;

        // в начале проверим не указанны ли демоны для остановки
        $daemons = input()->getOption('daemon', null);
        $daemons = array_filter(explode(',', $daemons));

        // если демоны не указаны, то берём только тех кто связан с указанными пользователями
        if (!$daemons) {
            $users = input()->getOption('user', null);
            $users = array_filter(explode(',', $users));

            // если пусто, то наверно деплоят все виджеты
            if (empty($users)) {
                note('Widgets not provided for deploy }:->');

                return true;
            }

            // Мы имеем названия пользователей, теперь стараемся найти те .pid-фвйлы,
            // которые принадлежат указанным пользователям по нзванию самих файлов.
            $tmp = [];
            foreach ($files as $file) {
                // 2ой элемент это имя пользователя
                $name = explode('-', $file);
                if (in_array($name[2], $users)) {
                    $tmp[] = $file;
                }
            }

            if (empty($tmp)) {
                note('Daemons do not exist for provided users }:->');

                return true;
            }

            $files = $tmp;
        } else {
            // прикрепляем расширение к имена демонов
            array_walk(
                $daemons,
                function (&$item, $key) {
                    $item .= '.pid';
                }
            );
            // зафильтруем, если вдруг указали несуещствующих демонов
            $files = array_intersect($filesSource, $daemons);
        }

        // Всегда при каждом деплои надо перезапускать общую очередь, а там
        // много обработчиков вида prod-mmcoexpo-console-closure_loop_**.pid.
        foreach ($filesSource as $file) {
            if (strpos($file, 'prod-mmcoexpo-console-closure_loop')) {
                $files[] = $file;
            }
        }

        if (!empty($files)) {
            foreach ($files as $file) {
                $pathFile = $path . $file;
                $pid      = run("cat {$pathFile}");

                if (run('[ $(ps -p ' . $pid . ' -o comm=) ] &&  printf "1" || printf "0"')->toString()) {
                    // попробуем убить терминейтом с ожиданием завершения в противном случае kill -9
                    $killed = false;

                    run("kill {$pid}");

                    for ($i = 1; $i <= 10; $i++) {
                        // Всего максимум будем ожидать 55 секунд
                        run('sleep ' . $i);

                        if (!run('[ $(ps -p ' . $pid . ' -o comm=) ] &&  printf "1" || printf "0"')->toString()) {
                            $killed = true;

                            break;
                        } else {
                            note('Daemon with pid ' . $pid . ' not killed – waiting ' . ($i + 1) . ' seconds }:->');
                        }
                    }

                    // Если процесс с данным пидом ещё висит то убъём жёстко
                    if (!$killed
                        && run('[ $(ps -p ' . $pid . ' -o comm=) ] &&  printf "1" || printf "0"')->toString()) {
                        run("kill -9 {$pid}");
                    }

                    note('Daemon with pid ' . $pid . ' killed }:->');
                }

                run("rm {$pathFile}");
            }
        } else {
            note('Daemons do not exist (files) }:->');
        }
    }
)->desc('Terminate daemons of old mmcoexpo release');

/**
 * Перезапуск определённых демонов, именование домнов по следующему приципу:
 * <env>-user-<username>-<controller>_<action>
 * prod-user-moevideocrm-onlinepbx_run
 */
task(
    'daemons:restart',
    function () {
        note('Started killing the daemons }:->');

        // в начале проверим не указанны ли демоны для остановки
        $daemons = input()->getOption('daemon', null);
        $users   = input()->getOption('user', null);

        if (!$daemons && !$users) {
            note('Daemons did not specified }:->');

            return true;
        }

        set('deploy_end', true);
    }
)->desc('Reload daemons');

/**
 * Показываем список демонов у кого созданы .pid-файлы
 */
task(
    'daemons:list',
    function () {
        if (!($path = get('mmcoexpo_current'))) {
            note('The previous release was not }:->');

            return true;
        }

        $path .= '/daemon/';

        // если папка с демонами не существует, то всё заканчиваем
        if (!(run('[ -d "' . $path . '" ] && printf "1" || printf "0"')->toString())) {
            note('Daemons do not exist }:->');

            return true;
        }

        // получаем все .pid-файлы, чтобы позже зафильтровать их
        $files = explode(PHP_EOL, run("ls {$path}"));

        $users = input()->getOption('user', null);
        $users = array_filter(explode(',', $users));

        // Если пользователи переданы, то зафильтруем по ним демонов
        if (!empty($users)) {
            $newFiles = [];

            foreach ($files as $file) {
                foreach ($users as $user) {
                    if (strpos($file, $user) !== false) {
                        $newFiles[] = $file;

                        break;
                    }
                }
            }

            $files = $newFiles;
        }

        rsort($files);

        $found = false;
        // Перебираем файлы, проверяем наличие процесса и сразу выводим в список
        foreach ($files as $file) {
            $file = trim($file);

            if (empty($file)) {
                continue;
            }

            $pathFile = $path . $file;
            $pid      = run("cat {$pathFile}");

            if (run('[ $(ps -p ' . $pid . ' -o comm=) ] &&  printf "1" || printf "0"')->toString()) {
                note(explode('.', $file)[0]);

                $found = true;
            } else {
                // Если нет процесса, то удалим файл
                run("rm {$pathFile}");
            }
        }

        if (!$found) {
            note('Daemons do not exist at all }:->');

            return true;
        }
    }
)->desc('List of daemons');

/**
 * Create symlinks for shared directories and files.
 */
task(
    'deploy:shared',
    function () {
        $release = get('mmcoexpo_symlink') . '/' . get('mmcoexpo_release');

        foreach (get('shared_dirs') as $dir) {
            // Remove from source
            run("if [ -d $(echo {$release}/$dir) ]; then rm -rf {$release}/$dir; fi");
            // Create shared dir if it does not exist
            run("mkdir -p {{shared}}/$dir");
            // Create path to shared dir in release dir if it does not exist
            // (symlink will not create the path and will fail otherwise)
            run("mkdir -p `dirname {$release}/$dir`");
            // Symlink shared dir to release dir
            run("ln -nfs {{shared}}/$dir {$release}/$dir");

            note('Shared "' . $dir . '"');
        }

        foreach (get('shared_files') as $file) {
            // Remove from source
            run("if [ -f $(echo {$release}/$file) ]; then rm -rf {$release}/$file; fi");
            // Create dir of shared file
            run("mkdir -p {{shared}}/" . dirname($file));
            // Touch shared
            run("touch {{shared}}/$file");
            // Symlink shared dir to release dir
            run("ln -nfs {{shared}}/$file {$release}/$file");

            note('Shared "' . $file . '"');
        }
    }
)->desc('Creating symlinks for shared files');

/**
 * Make writable dirs.
 */
task(
    'deploy:writable',
    function () {
        $dirs     = join(' ', get('writable_dirs'));
        $sudo     = get('writable_use_sudo') ? 'sudo' : '';
        $httpUser = get('http_user');
        $release  = get('mmcoexpo_symlink') . '/' . get('mmcoexpo_release');

        if (!empty($dirs)) {
            try {
                if (null === $httpUser) {
                    $httpUser = run(
                        "ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1"
                    )->toString();
                }

                cd("{$release}");

                if (strpos(run("chmod 2>&1; true"), '+a') !== false) {
                    if (!empty($httpUser)) {
                        run(
                            "$sudo chmod +a \"$httpUser allow delete,write,append,file_inherit,directory_inherit\" $dirs"
                        );
                    }

                    run("$sudo chmod +a \"`whoami` allow delete,write,append,file_inherit,directory_inherit\" $dirs");
                } elseif (commandExist('setfacl')) {
                    if (!empty($httpUser)) {
                        if (!empty($sudo)) {
                            run("$sudo setfacl -R -m u:\"$httpUser\":rwX -m u:`whoami`:rwX $dirs");
                            run("$sudo setfacl -dR -m u:\"$httpUser\":rwX -m u:`whoami`:rwX $dirs");
                        } else {
                            // When running without sudo, exception may be thrown
                            // if executing setfacl on files created by http user (in directory that has been setfacl before).
                            // These directories/files should be skipped.
                            // Now, we will check each directory for ACL and only setfacl for which has not been set before.
                            $writeableDirs = get('writable_dirs');
                            foreach ($writeableDirs as $dir) {
                                // Check if ACL has been set or not
                                $hasfacl = run("getfacl -p $dir | grep \"^user:$httpUser:.*w\" | wc -l")->toString();
                                // Set ACL for directory if it has not been set before
                                if (!$hasfacl) {
                                    run("setfacl -R -m u:\"$httpUser\":rwX -m u:`whoami`:rwX $dir");
                                    run("setfacl -dR -m u:\"$httpUser\":rwX -m u:`whoami`:rwX $dir");
                                }
                            }
                        }
                    } else {
                        run("$sudo chmod 777 -R $dirs");
                    }
                } else {
                    run("$sudo chmod 777 -R $dirs");
                }
            } catch (\RuntimeException $e) {
                $formatter    = \Deployer\Deployer::get()->getHelper('formatter');
                $errorMessage = [
                    "Unable to setup correct permissions for writable dirs.                  ",
                    "You need co configure sudo's sudoers files to don't prompt for password,",
                    "or setup correct permissions manually.                                  ",
                ];
                write($formatter->formatBlock($errorMessage, 'error', true));
                throw $e;
            }

            note('Writable "' . $dirs . '"');
        }
    }
)->desc('Make writable dirs');

/**
 * Make list of a cron-tasks
 */
task(
    'cron:assemble',
    function () {
        if (input()->getOption('cron', null)) {
            note('Skip this time (cron:assemble) because mmcoexpo');

            return true;
        }

        $users = input()->getOption('user', null);
        $users = array_filter(explode(',', $users));

        $daemons = input()->getOption('daemon', null);
        $daemons = array_filter(explode(',', $daemons));

        if (empty($users)) {
            if (!empty($daemons)) {
                $users = [];

                // Пытаемся найти имя пользователей в названиях демонов
                foreach ($daemons as $daemon) {
                    $name = explode('-', $daemon);
                    if (!in_array($name[2], $users)) {
                        $users[] = $name[2];
                    }
                }
            } elseif (get('deploy_type') == '_mmcoexpo') {
                $users = [];
            } else {
                note('Assemble cront-tasks of users do not need');

                return true;
            }
        }

        $paths = getCronPaths($users);
        $tasks = getCronTasks(array_column($paths, 'name'));

        // В $tasks должны остаться только те задачи, что сейчас не деплояться, после
        // них гарантируем два перенос строки, что будет создавать отделяющую строку.
        $tasks = trim(implode("\n", $tasks), "\n");
        if ($tasks) {
            $tasks .= "\n\n";
        }

        if (!empty($paths)) {
            $tmp = '';
            foreach ($paths as $path) {
                // Всегда добавляем два переноса строки с помощью <(echo -e "\n") для разделения пользователей.
                // Это требуется для понимания какие такски относяться к текущему деплою, а какие нет.
                $tmp .= ' <(echo "# ' . $path['name'] . '") ' . $path['path'] . ' <(echo -e "\n") ';
            }

            if ($tasks) {
                run('cat <(echo "' . $tasks . '") ' . $tmp . ' | crontab');
            } else {
                run('cat ' . $tmp . ' | crontab');
            }

            note('Created cron-tasks');

            $newTasks = run('cat ' . $tmp . ' | grep -v "^#" | grep ":daemon" | cut -f 6- -d " "')->toString();

            // сразу перезапускаем демоны, на тот случай если какие-то были остановлены на деплой
            $daemons = array_filter(explode(PHP_EOL, $newTasks));

            foreach ($daemons as $dmn) {
                note('Restart "' . $dmn . ' >/dev/null"');
                run($dmn . ' >/dev/null');
            }
            note('Restarted daemons');
        } else {
            note('New cron-tasks not exists');
        }
    }
)->desc('Make list of a cron-tasks');

/**
 * Clear Opcache
 */
task(
    'opcache:clear',
    function () {
        file_get_contents('https://pannel.mmco-expo.ru/opcache.php');
        run('php -r "opcache_reset();"');
    }
)->desc('Clear opcache');

/**
 * Send notice to Slack
 */
task(
    'slack:end_of_deploy',
    function () {
        if (!get('deploy_type')) {
            error('Notice to Slack failed!');

            return false;
        }

        if (get('deploy_type') == '_mmcoexpo') {
            $textSlack = 'Деплой ядра mmcoexpo завершён.';
        } else {
            $user      = input()->getOption('user', get('deploy_type'));
            $textSlack = 'Деплой пользователя "' . $user . '" завершён.';
        }

        $channelSlack = 'error';
        $userSlack    = 'Santa Claus';
        $icon         = ':santa:';

        $data = [
            "username"   => $userSlack,
            "link_names" => 1,
            "channel"    => "#" . $channelSlack,
            "icon_emoji" => $icon,
            "text"       => $textSlack,
        ];

        $data = "payload=" . json_encode($data);

        $ch = curl_init("https://hooks.slack.com/services/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");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result == 'ok';
    }
)->desc('Send notice to Slack');

/**
 * Проверка существования директории задеплоиного пользователя
 */
function userExists($user)
{
    return (bool)run('[ -d "{{release}}/' . $user . '" ] && printf "1" || printf "0"')->toString();
}

/**
 * получаем реальный путь до текущего релиза mmcoexpo
 */
function getMmcoexpoCurrent()
{
    return run("readlink -n {{mmcoexpo}}");
}

/**
 * получаем реальный путь до текущего релиза пользователя
 *
 * @param string $user
 */
function getUserCurrent($user)
{
    return run("readlink -n {{user}}/$user");
}

/**
 * получаем массив всех релизов mmcoexpo
 */
function getMmcoexpoReleases()
{
    $releases = explode(PHP_EOL, run("ls {{mmcoexpo_symlink}}"));
    rsort($releases);

    return array_filter(
        $releases,
        function ($release) {
            $release = trim($release);

            return !empty($release);
        }
    );
}

/**
 * получаем массив всех релизов пользователя
 *
 * @param string $user
 */
function getUserReleases($user)
{
    $releases = explode(PHP_EOL, run("ls {{release}}/$user"));
    rsort($releases);

    return array_filter(
        $releases,
        function ($release) {
            $release = trim($release);

            return !empty($release);
        }
    );
}

/**
 * получаем массив всех задеплоиных пользователей
 *
 * @param string $user
 *
 * @deprecated
 */
function getUsers()
{
    return array_filter(explode(PHP_EOL, run("ls {{user}}")));
}

/**
 * Возвращает массив путей до всех крон-файлов существующих пользователей и ядра.
 *
 * @param array $users
 *
 * @return array
 */
function getCronPaths($users)
{
    // нужно сделать один большой список крон-задач из всех задач задеплоеных пользователей
    // плюс крон-задачи виджетов и самого фреймворка mmcoexpo
    $paths = [];

    // крон-задачи mmcoexpo
    $path = get('mmcoexpo') . '/app/resource/config/cron';
    if (run('[ -f "' . $path . '" ] && printf "1" || printf "0"')->toString()) {
        $paths['mmcoexpo'] = [
            'path' => $path,
            'name' => 'mmcoexpo',
        ];
    }

    if ($users) {
        foreach ($users as $user) {
            // проверим есть ли задеплоеная директория данного пользователя (он может быть только в разработке)
            if (!userExists($user)) {
                continue;
            }

            $path = getUserCurrent($user) . '/resource/config/cron';

            if (run('[ -f "' . $path . '" ] && printf "1" || printf "0"')->toString()) {
                $paths[] = [
                    'path' => $path,
                    'name' => $user,
                ];
            }
        }
    }

    return $paths;
}

/**
 * Возвращает массив cron-задач из crontab за исключением задач переданных
 * пользователей. Cron-файлы собираются в задеплоиных пользователей.
 *
 * Все существующие cron-таски на момент деплоя, который надо отфильтровать
 * удалив таски тех пользователей, что сейчас деплоятся. Разделение одного
 * от другого происходит с помощью пустых строк потому их наличие очень важно.
 *
 * @param array $users
 *
 * @return array
 */
function getCronTasks($users)
{
    $tasks = explode(PHP_EOL, run('crontab -l')->toString());

    foreach ($users as $user) {
        $nubmer = array_search('# ' . $user, $tasks);

        if ($nubmer !== false) {
            $countTasks = count($tasks);

            for ($i = $nubmer; $i < $countTasks; $i++) {
                unset($tasks[$i]);

                $next = $i + 1;
                if ($countTasks == $next || $tasks[$next] == '') {
                    if (isset($tasks[$next]) && $tasks[$next] == '') {
                        unset($tasks[$next]);
                    }

                    $tasks = array_values($tasks);

                    break;
                }
            }
        }
    }

    return $tasks;
}

/**
 * возвратить содержимое файла окружения и заменить на новое, если указано
 *
 * @param string $file
 * @param string $new - имя нового окружения
 */
function setEnv($file, $new = null)
{
    // определяем есть ли файл окружения и если есть, то запоминаем старое значение
    $oldEnv = null;
    $exists = runLocally('[ -f "' . $file . '" ] && printf "1" || printf "0"')->toString();

    if ($exists) {
        $oldEnv = runLocally('cat ' . $file);
    }

    // устанавливаем новое окружение или удаляем файл, если ничего
    if ($new) {
        runLocally('printf "' . $new . '" > ' . $file);
    } elseif ($exists) {
        runLocally('rm -f ' . $file);
    }

    return $oldEnv;
}

/**
 * выводим сообщение-ошибку
 *
 * @param string $string
 */
function error($string)
{
    writeln('- ' . $string);
}

/**
 * выводим сообщение-предупреждение
 *
 * @param string $string
 */
function warning($string)
{
    writeln('! ' . $string);
}

/**
 * выводим сообщение-пометку
 *
 * @param string $string
 */
function note($string)
{
    writeln('+ ' . $string);
}

// перед деплоем mmcoexpo проведём тесты
before('deploy:mmcoexpo', 'test:mmcoexpo');
// Откатываем деплой ядра в случае возникновения ошибки
after('deploy:mmcoexpo', 'mmcoexpo:rollback_fail');
// После депля ядра убиваем все демоны (если нет деплоя пользователей)
after('deploy:mmcoexpo', 'daemons:terminate');
// После ядра всегда пытаемся деплоить пользователей
after('deploy:mmcoexpo', 'deploy:user');
// пошарим файлы и папки после релиза
after('deploy:mmcoexpo', 'deploy:shared');
// сделаем writeble папки после релиза
after('deploy:mmcoexpo', 'deploy:writable');
// после окончательного деплоя mmcoexpo переключаем симлинк
// все остальные таски будут выполнятся уже на включенном
after('deploy:mmcoexpo', 'switch:mmcoexpo');
// почистим релизы mmcoexpo, если надо
after('deploy:mmcoexpo', 'mmcoexpo:cleanup');
// после деплоя mmcoexpo собираем список cron-задач
after('deploy:mmcoexpo', 'cron:assemble');
// Чистим Opcache после деплоя mmcoexpo
after('deploy:mmcoexpo', 'opcache:clear');

// До деплоя пользователей прогоняем тесты
before('deploy:user', 'test:user');
// Откатываем деплой пользователя в случае возникновения ошибки
after('deploy:user', 'user:rollback_fail');
// После деплоя пользователя убиваем все демоны
after('deploy:user', 'daemons:terminate');
// после деплоя пользователей собираем список cron-задач
after('deploy:user', 'cron:assemble');
// Чистим Opcache после деплоя пользователей
after('deploy:user', 'opcache:clear');
// Чистим старые релизы
after('deploy:user', 'user:cleanup');

// После удаления пользователя убиваем все демоны
after('remove:user', 'daemons:terminate');
// Чистим Opcache после удаления пользователей
after('remove:user', 'opcache:clear');

// Просто делаем алиас для команды, вокруг которой ещё паруочка команд
after('daemons:restart', 'daemons:terminate');
// После убийства требуемых демонов перезапускаем их все
after('daemons:restart', 'cron:assemble');
