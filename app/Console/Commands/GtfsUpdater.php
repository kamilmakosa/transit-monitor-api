<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GtfsUpdater extends Command
{
    private $path = "/storage/gtfs";
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gtfs:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update GTFS in database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::table('logs')->insert([
            'log_key' => 'command_run',
            'log_value' => 'gtfs:update'
        ]);
        $old = DB::table('logs')->orderBy('log_time', 'DESC')->where('log_key', 'db_upload')->limit(1)->get();
        $filename = $this->download();
        $new = explode('_', $filename);
        if($new[0] <= $old[0]->log_value || $new[0] > date('Ymd')) {
            return 0;
        }

        $this->extract($filename);
        $handle = opendir('./'.$this->path.'/'.$filename);
        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $entry != "feed_info.txt") {
                    if(!is_file('./'.$this->path.'/'.$filename.'/'.$entry)) {
                        continue;
                    }
                    $file_parts = pathinfo($entry);
                    $this->uploadFile($file_parts['filename'], $this->path.'/'.$filename, $entry);
                }
            }
            closedir($handle);
        }
        DB::table('logs')->insert([
            'log_key' => 'db_upload',
            'log_value' => $new[0]
        ]);
    }

    private function download() {
        $url = "https://www.ztm.poznan.pl/pl/dla-deweloperow/getGTFSFile?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJ0ZXN0Mi56dG0ucG96bmFuLnBsIiwiY29kZSI6MSwibG9naW4iOiJtaFRvcm8iLCJ0aW1lc3RhbXAiOjE1MTM5NDQ4MTJ9.ND6_VN06FZxRfgVylJghAoKp4zZv6_yZVBu_1-yahlo";
        exec('curl -O -J "'.$url.'"');
        if ($handle = opendir('.')) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    if(!is_file($entry)) {
                        continue;
                    }
                    $file_parts = pathinfo($entry);
                    if (isset($file_parts['extension']) && $file_parts['extension'] == 'zip') {
                        if (!is_dir('./'.$this->path)) {
                            mkdir('./'.$this->path);
                        }
                        rename('./'.$entry, './storage/gtfs/'.$entry);
                        break;
                    }
                }
            }
            closedir($handle);
        }
        return $file_parts['filename'];
    }

    private function extract($filename) {
        $zip = new \ZipArchive;
        $filepath = realpath('./'.$this->path.'/'.$filename.'.zip');
        if ($zip->open($filepath) === TRUE) {
            if (!is_dir('./'.$this->path.'/'.$filename)) {
                mkdir('./'.$this->path.'/'.$filename);
            }
            $filepath = realpath('./'.$this->path.'/'.$filename);
		    $zip->extractTo($filepath);
		    $zip->close();
		    return true;
		}
        return false;
    }

    private function uploadFile($table, $path, $filename) {
        $delimiter = ',';
        $enclosed = "\"";

        $this->info('Processing: '.$filename.' to gtfs.'.$table);
        $file = fopen('./'.$path.'/'.$filename, "r") or exit("Unable to open file!");
        $filedata = trim(fgets($file));
        if($delimiter != ',') {
            $filedata = str_replace($delimiter, ',', $filedata);
        }
		$enclosed_text = ($enclosed) ? "OPTIONALLY ENCLOSED BY '$enclosed'" : '';
        $path = realpath('./'.$path);
		$dir = str_replace('\\',"/",$path);
		$re = '/[^a-zA-Z0-9,_]/';
		$subst = '';
		$filedata = preg_replace($re, $subst, $filedata);
		$query = "LOAD DATA LOCAL INFILE '$dir/$filename' INTO TABLE $table FIELDS TERMINATED BY '$delimiter' $enclosed_text LINES TERMINATED BY '\r\n' IGNORE 1 LINES ($filedata);";

        DB::table($table)->truncate();
        DB::table('logs')->insert([
            'log_key' => 'table_truncate',
            'log_value' => $table
        ]);
        $pdo = DB::connection()->getPdo();
        $pdo->exec($query);
        $this->info('Uploaded: '.$filename.' to gtfs.'.$table);
        DB::table('logs')->insert([
            'log_key' => 'table_load-data',
            'log_value' => $table
        ]);
    }
}