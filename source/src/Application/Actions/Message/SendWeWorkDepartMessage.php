<?php
declare(strict_types=1);
namespace App\Application\Actions\Message;

use App\Domain\Message\Service\WeWorkCreator;
use App\Domain\Message\Service\WeWorkSenderHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SendWeWorkDepartMessage
{

    public function __construct(WeWorkCreator $weWorkCreator)
    {
        $this->weWorkCreator = $weWorkCreator;
    }

    public function __invoke(ServerRequest $request, Response $response): Response
    {
        $departmentId = $request->getAttribute('partyid');
        $msg = $request->getAttribute('msg');
        //$response->getBody()->write("Hello, $msg");

        $this->weWorkCreator->create('department', $departmentId);
        return $response;

    }

}