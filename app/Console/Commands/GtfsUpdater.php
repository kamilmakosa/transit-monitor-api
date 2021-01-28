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
    protected $signature = 'gtfs:update {--reload}';

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
            'log_value' => 'gtfs:update',
            'log_text' => (($this->option('reload')) ? 'reload' : '')
        ]);
        $list = $this->listGtfs();
        $isUpdate = false;
        foreach($list as $files) {
            if($files['start'] == date('Ymd') || ($this->option('reload') && $files['start'] < date('Ymd'))) {
                $isUpdate = true;
                $file = $files['file'];
                break;
            }
        }
        if(!$isUpdate) {
            return 0;
        }
        $this->info('Download file: '.$file);
        $filename = $this->download($file);
exit;
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
            'log_value' => $file
        ]);
    }

    private function listGtfs() {
        $html = file_get_contents('https://www.ztm.poznan.pl/pl/dla-deweloperow/gtfsFiles');
        $re = '/[\n\t]/m';
        $html = preg_replace($re, '', $html);
        $re = '/<td>([0-9]+_[0-9]+)\.zip<\/td>/U';
        preg_match_all($re, $html, $matches, PREG_SET_ORDER, 0);
        foreach($matches as $match) {
            list($start, $end) = explode('_', $match[1]);
            $list[] = array(
                'start' => $start,
                'end' => $end,
                'file' => $match[1].'.zip'
            );
        }
        return $list;
    }

    private function download($file) {
        $url = "https://www.ztm.poznan.pl/pl/dla-deweloperow/getGTFSFile/?file=".$file;
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
