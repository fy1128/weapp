<?php
namespace App\Domain\Message\Service;
use EasyWeChat\Factory;
use Psr\Container\ContainerInterface;
use Selective\Config\Configuration;
use Slim\Factory\AppFactory;

Class WeWorkCreator {

    public function create($type, $agentid = null)
    {

        $app = AppFactory::create();
        $container = $app->getContainer();
        $settings = $container->get(Configuration::class)->getArray('we_work');

        if ($type == 'app') {
            if (empty($settings['apps'][$agentid])) {
                throw new \InvalidArgumentException('Invalid agentid.');
            }
            $settings['app'] = $settings['apps'][$agentid];
        } else {
            $settings['app'] = [
                "agentid" => null,
                "secret" => $settings[$type]["secret"]
            ];
        }
        
        $container->set('we_work_config', $settings);

        $container->set('WeWork', function(ContainerInterface $c) {
            $settings = $c->get('we_work_config');
            $config = [
                'corp_id' => $settings['corp_id'],

                'agent_id' => (int)$settings['app']['agentid'], // 如果有 agend_id 则填写
                'secret'   => $settings['app']['secret'],

                // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
                'response_type' => $settings['response_type'],

                'log' => $settings['log']
            ];

            $weWork = Factory::work($config);
            return $weWork;
        });

        return $container->get('WeWork');
    }

}