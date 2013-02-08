#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Class for managing lights via Call of Duty server log
 */
class HueCodLogger
{
    /**
     * Settings
     * 
     * @var array
     */
    protected $settings;

    /**
     * Phue client
     * 
     * @var \Phue\Client
     */
    protected $phue;

    /**
     * Log stream
     *
     * @var resource
     */
    protected $log;

    /**
     * Log event callbacks
     *
     * @var array
     */
    protected $eventCallbacks = [];

    /**
     * Run the script
     *
     * @return void
     */
    public function run()
    {
        $this->getSettings();
        $this->showIntro();
        $this->getPhueClient();
        $this->turnOffAllLights();
        $this->testEachLight();
        $this->registerStandardEvents();
        $this->startTailingLog();
    }

    /**
     * Show intro
     *
     * @return void
     */
    public function showIntro()
    {
        echo "Philips Hue controller for CoD: Modern Warfare", "\n",
             "---------------------------------------", "\n";
    }

    /**
     * Get Phue client
     *
     * @return \Phue\Client Phue client
     */
    public function getPhueClient()
    {
        echo "Testing connection to Philips Hue: ";

        try {
            // Set up phue client
            $phue = new \Phue\Client(
                $this->settings['philips_hue']['host'],
                $this->settings['philips_hue']['user']
            );

            $phue->getTransport()->setAdapter(new \Phue\Transport\Adapter\Curl);
            $phue->sendCommand(new \Phue\Command\Ping);
            $this->phue = $phue;

            echo "Success!", "\n";
        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}", "\n";
            exit(1);
        }

        return $phue;
    }

    /**
     * Turn off all lights
     *
     * @return void
     */
    public function turnOffAllLights()
    {
        // Turn off all lights
        (new \Phue\Command\SetGroupAction(0))->on(false)->send($this->phue);
    }

    /**
     * Test each light
     *
     * @return void
     */
    public function testEachLight()
    {
        // Test each light
        foreach ([1, 2, 3] as $lightId) {
            (new \Phue\Command\SetLightState($lightId))->on(true)->brightness(254)->transitionTime(0)->send($this->phue);
            usleep(20000);
            (new \Phue\Command\SetLightState($lightId))->on(false)->brightness(0)->transitionTime(0)->send($this->phue);
            usleep(20000);
        }
    }

    /**
     * Register standard events
     *
     * @return void
     */
    public function registerStandardEvents()
    {
        // Game started
        $this->registerEventCallback(
            'game.started',
            '/^\s*\d+:\d+ InitGame:/is',
            function () {
                $this->alertGreenLight();
            }
        );

        // Game ended
        $this->registerEventCallback(
            'game.ended',
            '/^\s*\d+:\d+ ShutdownGame:/is',
            function () {
                $this->fadeOutGreenLight();
            }
        );

        // Someone died
        $this->registerEventCallback(
            'player.death',
            '/^\s*\d+:\d+ K;/is',
            function () {
                $this->showRedLight();
            }
        );

        // Someone took fall damage
        $this->registerEventCallback(
            'player.damaged.fall',
            '/^\s*\d+:\d+ D;(.*?);MOD_FALLING;/is',
            function () {
                $this->blinkYellowLight(128);
            }
        );

        // Someone took other damage
        $this->registerEventCallback(
            'player.damaged.standard',
            '/^\s*\d+:\d+ D;/is',
            function () {
                $this->blinkYellowLight();
            }
        );
    }

    /**
     * Register event callback
     *
     * @param string   $name     Name
     * @param string   $pattern  Regex
     * @param Callable $callback Closure
     *
     * @return void
     */
    public function registerEventCallback($name, $pattern, Callable $callback)
    {
        $this->eventCallbacks[$name] = [
            'pattern'  => $pattern,
            'callback' => $callback
        ];
    }

    /**
     * Handle message
     *
     * @param string $message Log message
     *
     * @return void
     */
    protected function handleMessage($message)
    {
        foreach ($this->eventCallbacks as $name => $event) {
            // Check next event if no match
            if (!preg_match($event['pattern'], $message)) {
                continue;
            }

            // Show message
            echo "Dispatching: {$name}", "\n";

            // Run callback
            $event['callback']();

            return;
        }
    }

    /**
     * Start tailing log
     *
     * @return void
     */
    public function startTailingLog()
    {
        $this->openLog();

        while (true) {
            $message = fgets($this->log);
            $this->handleMessage($message);
        }
    }

    /**
     * Show red light
     *
     * @return void
     */
    protected function showRedLight()
    {
        (new \Phue\Command\SetLightState(1))->on(true)->brightness(254)->transitionTime(0)->send($this->phue);
        (new \Phue\Command\SetLightState(1))->on(false)->brightness(0)->transitionTime(3)->send($this->phue);
    }

    /**
     * Blink yellow light
     *
     * @param int $brightness Brightness level
     *
     * @return void
     */
    protected function blinkYellowLight($brightness = 254)
    {
        (new \Phue\Command\SetLightState(2))->on(true)->brightness($brightness)->transitionTime(0)->send($this->phue);
        usleep(10000);
        (new \Phue\Command\SetLightState(2))->on(false)->brightness(0)->transitionTime(0)->send($this->phue);
    }

    /**
     * Alert green light
     *
     * @return void
     */
    protected function alertGreenLight()
    {
        (new \Phue\Command\SetLightState(3))->on(true)->alert('lselect')->brightness(128)->transitionTime(1)->send($this->phue);
    }

    /**
     * Fade out green light
     *
     * @return void
     */
    protected function fadeOutGreenLight()
    {
        (new \Phue\Command\SetLightState(3))->on(false)->brightness(0)->transitionTime(1)->send($this->phue);
    }

    /**
     * Get settings
     *
     * @return array Settings
     */
    protected function getSettings()
    {
        // Parse settings and get values if haven't already
        if (!$this->settings) {
            $this->settings = parse_ini_file(__DIR__ . '/settings.ini', true);
        }

        return $this->settings;
    }

    /**
     * Open the log
     *
     * @return void
     */
    protected function openLog()
    {
        $this->log = popen(
            'tail -f -n0 ' . escapeshellarg($this->settings['call_of_duty']['log_path']) . ' 2>&1',
            'r'
        );
    }

    /**
     * Close the log
     *
     * @return void
     */
    protected function closeLog()
    {
        pclose($this->log);
    }

    /**
     * Object destruct
     *
     * @return void
     */
    public function __destruct()
    {
        $this->closeLog();
    }
}

(new HueCodLogger)->run();
