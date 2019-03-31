<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Messages\Text;
use EasyWeChat\Kernel\Messages\Media;
use EasyWeChat\Kernel\Messages\Raw;
use Slim\App;
use Slim\Http\UploadedFile;

define('WEAPP', true);

require __DIR__ . '/../vendor/autoload.php';

class WEAPP
{
    protected $config;
    protected $app;

    public function __construct()
    {
        $this->config = require 'config.php';
        $settings = $this->config['app'];
        $this->app = new App(["settings" => $settings]);
        $container = $this->app->getContainer();
        $container['weApp'] = function($c) {
            return $this;
        };
    }
    
    public function init() {
        $this->app->group('/work/app/send', function()
        {
            $this->map(['GET', 'POST'], '[/{agentid}/{msg}[/{type}/{typeid}]]', function (Request $request, Response $response) {
                if ($request->isPost()) {
                        $parsedBody = $request->getParsedBody();
                        //var_dump($parsedBody);
                        $agentid = $parsedBody['agentid'];
                        $msg = $parsedBody['msg'];
                } else {
                    $agentid = $request->getAttribute('agentid');
                    $msg = $request->getAttribute('msg');
                }
                if (!$msg = base64_decode($msg, true)) {
                    throw new InvalidArgumentException('Invalid message.');
                };

                // https://stackoverflow.com/questions/1671785/in-php-whats-the-diff-between-stripcslashes-and-stripslashes
                $msg = stripcslashes(urldecode($msg));
                $messageSent = [];
                // type accept: user, depart
                if ($type = $request->getAttribute('type')) {
                    $typeid = $request->getAttribute('typeid');
                }
                
                $this->weApp->updateWeWork('app', $agentid);

                if (!empty($typeid)) {
                    $typeHandler = ['depart' => 'toParty', 'user' => 'toUser'];
                    $this->work->messenger->{$typeHandler[$type]}($typeid);
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
                            $this->weApp->moveUploadedFile($uploadedFile, $this->work, $mediaFiles);
                        }
                    }
                }

                if ($msg_arr = json_decode($msg, true)) {
                    // Beware, json_decode will return true for any number also.
                    if ($msg_arr != $msg) {
                        $message = new Raw($msg);
                        $messageSent[] = $this->work->messenger->message($message)->send();
                    }
                } else {
                    $message = new Text($msg);
                    //$msg = str_replace('\n', "\n", $msg);
                    $messageSent[] = $this->work->messenger->message($msg)->send();
                }
                
                // append media message
                if (!empty($mediaFiles)) {
                    foreach ($mediaFiles as $mediaType => $mediaId) {
                        $media = new Media($mediaId, $mediaType);
                        $messageSent[] = $this->work->messenger->message($media)->send();
                    }
                }

                return $response->withJSON($messageSent, 200, JSON_UNESCAPED_UNICODE);
            })->setName('message');
        });

        $this->app->get('/work/depart/sync/{partyid}/', function (Request $request, Response $response)
        {
            $departmentId = $request->getAttribute('partyid');
            $msg = $request->getAttribute('msg');
            //$response->getBody()->write("Hello, $msg");

            $this->weApp->updateWeWork($this, 'department', $departmentId);
            return $response;
        });
        
        $this->app->run();
    }

    /**
     * Moves the uploaded file to the upload directory and assigns it a unique name
     * to avoid overwriting an existing uploaded file.
     *
     * @param string $directory directory to which the file is moved
     * @param UploadedFile $uploaded file uploaded file to move
     * @return string filename of moved file
     */
    public function moveUploadedFile(UploadedFile $uploadedFile, $weWork, &$mediaFiles)
    {

        $pathParts = pathinfo($uploadedFile->getClientFilename());
        //$basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
        $md5 = md5_file($uploadedFile->file);
        $filename = sprintf('%s.%0.8s', $pathParts['filename'] . '_' . substr($md5, 0, 10), $pathParts['extension']);

        $tmpDir = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
        $targetPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
        $uploadedFile->moveTo($targetPath);

        
        $mediaType = explode('/', $uploadedFile->getClientMediaType());
        $uploadType = 'uploadFile';
        $msgType = 'file';
        switch ($mediaType[0]) {
            case 'image':
                $uploadType = 'uploadImage';
                $msgType = 'image';
                break;
            case 'video':
                $uploadType = 'uploadVideo';
                $msgType = 'video';
                break;
            case 'audio':
                $uploadType = 'uploadVoice';
                $msgType = 'voice';
                break;
        }
        
        if ($res = $weWork->media->{$uploadType}($targetPath)) {
            if (!$res['errcode']) {
                $mediaFiles[$msgType] = $res['media_id'];
            }
        }
        unlink($targetPath);
        return $targetPath;
    }

    public function updateWeWork($type, $agentid)
    {
        $config = $this->config['we_work'];

        if ($type == 'app') {
            if (empty($config['apps'][$agentid])) {
                throw new InvalidArgumentException('Invalid agentid.');
            }
            $config['app'] = $config['apps'][$agentid];
        } else {
            $config['app'] = [
                "agentid" => null,
                "secret" => $config[$type]["secret"]
            ];
        }
        
        $container = $this->app->getContainer();
        $container['we_work'] = $config;

        $container['work'] = function($c) {
            $config = [
                'corp_id' => $c['we_work']['corp_id'],

                'agent_id' => (int)$c['we_work']['app']['agentid'], // 如果有 agend_id 则填写
                'secret'   => $c['we_work']['app']['secret'],

                // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
                'response_type' => $c['we_work']['response_type'],

                'log' => $c['we_work']['log']
            ];

            $weWork = Factory::work($config);
            return $weWork;
        };
    }

}

$weApp = new WEAPP();
$weApp->init();
