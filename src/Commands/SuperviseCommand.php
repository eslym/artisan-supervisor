<?php


namespace Eslym\Laravel\Supervisor\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use React\ChildProcess\Process;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Input\InputArgument;

class SuperviseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'supervisor:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run supervisor';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var Process[]
     */
    protected $workers = [];

    /**
     * @var string
     */
    private $php;

    /**
     * @var string
     */
    private $artisan;

    /**
     * Create a new command instance.
     *
     * @param Application $app
     * @param Config $config
     */
    public function __construct(Application $app, Config $config)
    {
        parent::__construct();
        $this->config = $config;
        $this->php = escapeshellcmd(PHP_BINARY);
        $this->artisan = $this->php . ' ' . escapeshellarg($app->basePath('artisan'));
    }

    protected function getArguments()
    {
        return [
            ['services', InputArgument::IS_ARRAY, 'Services to supervise']
        ];
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->prepareLoop();
        $this->prepareWorkers();
        $this->loop->futureTick(function () {
            foreach ($this->workers as $name => $worker) {
                $this->output->writeln("[$name][" . date('Y-m-d H:i:s') . "] Starting.");
                $worker->start($this->loop);
                if ($worker->stdout) {
                    $worker->stdout->on('data', function ($data) use ($name) {
                        $this->output->writeln("[$name]" . trim($data));
                    });
                }
                if ($worker->stderr) {
                    $worker->stderr->on('data', function ($data) use ($name) {
                        $this->output->writeln("[$name]" . trim($data));
                    });
                }
            }
        });
        $this->loop->run();
        return 0;
    }

    protected function prepareLoop()
    {
        $this->loop = LoopFactory::create();
    }

    protected function prepareWorkers()
    {
        $services = $this->config->get('supervisor.services');
        $run = $this->argument('services');
        if (count($run) == 0) {
            $run = array_keys($services);
        }
        foreach ($run as $service) {
            $cmd = $this->prepareCommand($this->config->get("supervisor.services.$service.command"));
            $replicas = $this->config->get("supervisor.services.$service.replicas", 1);
            $delay = $this->config->get("supervisor.services.$service.delay", 1);
            for ($i = 1; $i <= $replicas; $i++) {
                $name = Str::snake($service, '-') . '-' . $i;
                $this->workers[$name] = $this->makeWorker($name, $cmd, $delay);
            }
        }
    }

    /**
     * @param string $name
     * @param string $cmd
     * @param float $delay
     * @return Process
     */
    protected function makeWorker(string $name, string $cmd, float $delay)
    {
        $proc = new Process($this->makeCommand($name, $cmd), null, null);
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
        });
        return $proc;
    }

    /**
     * @param $cmd
     * @return string
     */
    protected function prepareCommand($cmd)
    {
        if (Str::startsWith($cmd, '@php')) {
            $cmd = str_replace('@php', $this->php, $cmd);
        } else if (Str::startsWith($cmd, '@artisan')) {
            $cmd = str_replace('@artisan', $this->artisan, $cmd);
        }
        return $cmd;
    }

    /**
     * @param string $name
     * @param string $cmd
     * @return string
     */
    protected function makeCommand(string $name, string $cmd)
    {
        return $cmd;
    }
}
