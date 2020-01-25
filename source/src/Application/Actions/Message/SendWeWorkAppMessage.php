<?php
declare(strict_types=1);
namespace App\Application\Actions\Message;

use App\Domain\Message\Service\WeWorkCreator;
use App\Domain\Message\Service\WeWorkSenderHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Http\UploadedFile;
use EasyWeChat\Kernel\Messages\Text;
use EasyWeChat\Kernel\Messages\Media;
use EasyWeChat\Kernel\Messages\Raw;

class SendWeWorkAppMessage
{
    
    public function __construct(WeWorkCreator $weWorkCreator)
    {
        $this->weWorkCreator = $weWorkCreator;
    }

    public function __invoke(Request $request, Response $response): Response
    {
    
        if ($request->getMethod() == 'POST') {
            $parsedBody = $request->getParsedBody();
            //var_dump($parsedBody);
            $agentid = $parsedBody['agentid'];
            $msg = $parsedBody['msg'];

            // type accept: user, depart
            $type = $parsedBody['type'];
            if (!empty($type)) {
                $typeid = $parsedBody['typeid'];
            }

        } else {
            $agentid = $request->getAttribute('agentid');
            $msg = $request->getAttribute('msg');

            if ($type = $request->getAttribute('type')) {
                $typeid = $request->getAttribute('typeid');
            }
        }
        if (!$msg = base64_decode($msg, true)) {
            throw new InvalidArgumentException('Invalid message.');
        };

        // https://stackoverflow.com/questions/1671785/in-php-whats-the-diff-between-stripcslashes-and-stripslashes
        $msg = stripcslashes(urldecode($msg));
        $messageSent = [];
        
        $weWork = $this->weWorkCreator->create('app', $agentid);

        if (!empty($typeid)) {
            $typeHandler = ['depart' => 'toParty', 'user' => 'toUser'];
            $weWork->messenger->{$typeHandler[$type]}($typeid);
        }

        // handlering uploaded files.
        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles['attach'])) {
            // handle single input with multiple file uploads
            $mediaFiles = [];
            if ($uploadedFiles['attach'] instanceof UploadedFile) {
                $attach = $uploadedFiles['attach'];
                unset($uploadedFiles['attach']);
                $uploadedFiles['attach'] = [$attach];
            }
            foreach ($uploadedFiles['attach'] as $uploadedFile) {
                if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                    WeWorkSenderHelper::moveUploadedFile($uploadedFile, $weWork, $mediaFiles);
                }
            }
        }

        // Beware, json_decode will return true for any number also.
        if (is_array(json_decode($msg, true)) && (json_last_error() == JSON_ERROR_NONE)) {
            $message = new Raw($msg);
            $messageSent[] = $weWork->messenger->message($message)->send();
        } else {
            $message = new Text($msg);
            //$msg = str_replace('\n', "\n", $msg);
            $messageSent[] = $weWork->messenger->message($msg)->send();
        }
        
        // append media message
        if (!empty($mediaFiles)) {
            foreach ($mediaFiles as $mediaType => $mediaId) {
                $media = new Media($mediaId, $mediaType);
                $messageSent[] = $weWork->messenger->message($media)->send();
            }
        }

        return $response->withJSON($messageSent, 200, JSON_UNESCAPED_UNICODE);

    }

}