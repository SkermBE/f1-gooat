<?php

namespace Deployer;

require 'recipe/craftcms.php';

// vars
$hostIp = 'ssh090.webhosting.be';
$repository = 'git@github.com:SkermBE/craftSkerm.git';
$user = 'skermbe';

// COMBELL
$deployPathBase = "/data/sites/web/{$user}/apps/";

// Config
set('keep_releases', 5);
set('http_user', $user);
set('writable_mode', 'chmod');
set('repository', $repository);

set('shared_files', [
    '.env',
]);

set('shared_dirs', [
    'storage',
    'translations',
    'web/uploads',
    '/web/cache'
]);

set('writable_dirs', [
    'web/uploads',
]);

// Hosts
host('production')
    ->setHostname($hostIp)
    ->setRemoteUser($user)
    ->set('deploy_path', $deployPathBase . '/production')
    ->set('branch', 'develop')
    ->set('labels', ['stage' => 'production']);

    
// Clear Craft template caches after deploy
task('craft:clear_caches', function () {
    run('cd {{release_path}} && php craft clear-caches/all');
});

// Flush opcache automaticly
task('deploy:flush_opcache', function () {
    $baseUrl = run("grep PRIMARY_SITE_URL {{release_path}}/.env | head -n 1 | cut -d '=' -f2- | tr -d '\"'");
    $flushUrl = rtrim($baseUrl, '/') . "/flush_opcache.php";
    runLocally("curl -s -o /dev/null -w \"%{http_code}\" \"$flushUrl\"");
});

// Extra tasks
after('deploy:symlink', 'craft:clear_caches'); 
after('deploy:success', 'deploy:flush_opcache');

// Failed
after('deploy:failed', 'deploy:unlock');