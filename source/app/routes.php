<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->group('/work/app/send', function(Group $group)
    {
        $group->map(['GET', 'POST'], '[/{agentid}/{msg}[/{type}/{typeid}]]', \App\Application\Actions\Message\SendWeWorkAppMessage::class);
    });

    $app->get('/work/depart/sync/{partyid}/', \App\Application\Actions\Message\SendWeWorkDepartMessage::class);

};
