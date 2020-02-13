<?php


namespace Eslym\Laravel\Supervisor\Commands\Windows;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\StreamOutput;

class PipeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'supervisor:pipe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pipe worker output command.';

    /**
     * @var Encrypter
     */
    protected $encrypter;

    /**
     * Create a new command instance.
     *
     * @param Encrypter $encrypter
     */
    public function __construct(Encrypter $encrypter)
    {
        parent::__construct();
        $this->encrypter = $encrypter;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $out = stream_socket_client($this->argument('up-stream'));

        // Auth message
        fwrite($out, $this->encrypter->encrypt((object)['name' => $this->argument('name')]));
        fflush($out);
        do{
            $piped = fread(STDIN, 4096);
            fwrite($out, $piped);
            fflush($out);
        }while(isset($piped[0]));
        return 0;
    }

    protected function getArguments()
    {
        return [
            ['up-stream', InputArgument::REQUIRED, 'Socket for stdout'],
            ['name', InputArgument::REQUIRED, 'Worker name'],
        ];
    }
}
