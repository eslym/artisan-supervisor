<?php


namespace Eslym\Laravel\Supervisor\Commands\Windows;

use Eslym\Laravel\Supervisor\Commands\SuperviseCommand as Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use React\ChildProcess\Process;
use React\Socket\ConnectionInterface;
use React\Socket\Server;
use Throwable;

/**
 * Class SuperviseCommand
 * @package Eslym\Laravel\Supervisor\Commands\Windows
 *
 * Because of windows blocking pipes problem,
 * it needs a different implementation.
 */
class SuperviseCommand extends Command
{
    /**
     * @var Encrypter
     */
    protected $encrypter;
    /**
     * @var Server
     */
    protected $server;

    /**
     * @var ConnectionInterface[]
     */
    protected $clients;

    /**
     * @var string
     */
    private $pipe;

    /**
     * SuperviseCommand constructor.
     * @param Application $app
     * @param Config $config
     * @param Encrypter $encrypter
     */
    public function __construct(Application $app,Config $config, Encrypter $encrypter)
    {
        parent::__construct($app, $config);
        $this->encrypter = $encrypter;
        $this->pipe = $this->prepareCommand('@artisan supervisor:pipe');
    }

    protected function makeCommand($name, $cmd)
    {
        $srv = escapeshellarg($this->server->getAddress());
        $name = escapeshellarg($name);
        return "cmd /c $cmd 2>&1 | $this->pipe $srv $name";
    }

    protected function makeWorker($name, $cmd, $delay)
    {
        $proc = new Process($this->makeCommand($name, $cmd), null, null, []);
        $proc->on('exit', function ($code) use ($proc, $name, $delay) {
            $this->output->writeln(
                "[$name]" . date('[Y-m-d H:i:s]') .
                " Exited with code $code, restart in $delay " .
                Str::plural('second', $delay)
            );
            $this->loop->addTimer($delay, function () use ($proc, $name) {
                $this->output->writeln("[$name]" . date('[Y-m-d H:i:s]') . " Restarting.");
                $proc->start($this->loop);
            });
            if (isset($this->clients[$name])) {
                $this->clients[$name]->end();
            }
        });
        return $proc;
    }

    protected function prepareLoop()
    {
        parent::prepareLoop();
        $this->prepareServer();
    }

    protected function prepareServer()
    {
        $this->server = new Server('127.0.0.1:0', $this->loop);
        $this->server->on('connection', function (ConnectionInterface $conn) {
            $timer = $this->loop->addTimer(10, function () use ($conn) {
                $conn->end();
            });
            $conn->once('data', function ($data) use ($conn, $timer) {
                $this->loop->cancelTimer($timer);
                try {
                    $data = $this->encrypter->decrypt($data);
                    if (!is_object($data) || !isset($data->name)) {
                        $conn->end();
                        return;
                    }
                    if (isset($this->clients['names'][$data->name])) {
                        $conn->end();
                        return;
                    }
                    $name = $data->name;
                    $this->clients[$name] = $conn;
                    $conn->on('data', function ($data) use ($name) {
                        $this->output->writeln("[$name]" . trim($data));
                    });
                    $conn->on('end', function () use ($name) {
                        unset($this->clients[$name]);
                    });
                } catch (Throwable $e) {
                    $conn->end();
                }
            });
        });
    }
}