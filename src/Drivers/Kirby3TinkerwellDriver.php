<?php

/* EXAMPLE kirby config for tinkerwell driver

return [
    // custom tinkerwell commands from kirbys config file
    // NOTE: changes need restart of tinkerwell
    'tinkerwell' => [ // submenu can be array or callback returnng an array
        'title' => '$site->title();', // submenu-items can be string or callback returning a string
        'count' => function() { return 'site()->pages()->count();'; },
    ],
];

*/

use Tinkerwell\ContextMenu\Label;
use Tinkerwell\ContextMenu\OpenURL;
use Tinkerwell\ContextMenu\SetCode;
use Tinkerwell\ContextMenu\Submenu;

class KirbyTinkerwellDriver extends TinkerwellDriver
{
    private $cli;
    private $bootstrap;

    private function fileCopyOutdated($source, $target): bool
    {
        if (file_exists($target) && filemtime($source) < filemtime($target)) {
            return false;
        }

        file_put_contents($target, file_get_contents($source));
        return true;
    }

    private function patchBootstrapFile($file, $search, $replace): bool
    {
        if (! file_exists($file)) {
            return false;
        }

        return file_put_contents($file,
                str_replace($search, $replace, file_get_contents($file))
            ) !== false;
    }

    private function setUp($projectPath)
    {
        // find kirby's root index file (may contain custom folder setup)
        foreach (['index.php', 'public/index.php'] as $file) {
            $file = $projectPath . '/' . $file;
            if (file_exists($file)) {
                $this->bootstrap = $file;
            }
        }

        // NEW file /kirby/tinkerwell.php
        // create cli bootstrap without calling kirby()->render
        // in creating a copy and commenting out the echo
        $this->cli = $projectPath . '/kirby/tinkerwell.php';
        if ($this->fileCopyOutdated($this->bootstrap, $this->cli)) {
            // comment out any echo in k2 or k3 bootstrap
            $this->patchBootstrapFile($this->cli, 
            	'echo', 
            	'// echo'
            );

            // patch k3 composer vendor require: https://github.com/getkirby/starterkit/blob/master/index.php#L3
            $this->patchBootstrapFile($this->cli, 
            	"require __DIR__ . '/kirby/", 
            	"require( realpath(__DIR__ . '/../') . '/kirby/"
            );

            // patch k2 composer vendor require: https://github.com/getkirby-v2/plainkit/blob/master/index.php#L6
            $this->patchBootstrapFile($this->cli, 
            	'require(__DIR__ . DS', 
            	"require( realpath(__DIR__ . DS . '..') . DS"
            );
        }
    }
    
    // NEW driver method
    public function templateDirs(): array
    {
        return [
            kirby()->roots()->templates(),
            kirby()->roots()->snippets(),
        ];
    }
    
    // NEW driver method
    public function templateFilename(): string
    {
        return 'tinker.php';
    }
    
    // NEW driver method
    public function canControllerReturnHtml(): bool
    {
        // return false; // for laravel since it needs a route
        return true; // for for kirby since controller directly return html (just like in cli mode)
    }

    public function canBootstrap($projectPath)
    {
        $this->setUp($projectPath);

        return file_exists($this->cli);
    }

    public function bootstrap($projectPath)
    {
        require $this->cli;
    }

    public function getAvailableVariables()
    {
        return [
            'site' => site(),
            'kirby' => kirby(),
        ];
    }

    public function contextMenu()
    {
        $menu = [
            Label::create('Detected PHP v.' . phpversion()),
            Label::create('Detected Kirby v.' . Kirby::version()),

            OpenURL::create('Kirby Guide', 'https://getkirby.com/docs/guide'),
            OpenURL::create('Kirby Cookbook', 'https://getkirby.com/docs/cookbook'),
            OpenURL::create('Kirby Reference', 'https://getkirby.com/docs/reference'),
            OpenURL::create('Kirby Guide', 'https://getkirby.com/search?q=&area=all'),
            OpenURL::create('Kirby Forum', 'https://forum.getkirby.com'),
            
        ];

        if ($tinkerwell = kirby()->option('tinkerwell')) {
            if (is_callable($tinkerwell)) {
                $tinkerwell = $tinkerwell();
            }
            $submenu = [];
            foreach($tinkerwell as $key => $value) {
                if (is_callable($value)) {
                    $value = $value();
                }
                $submenu[] = SetCode::create($key, $value);
            }
            $menu = array_merge($menu, [Submenu::create('kirby option tinkerwell', $submenu)]);
        }

        return $menu;
    }
}
