<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Page\Pages;

/**
 * Class IPRedirectPlugin
 * @package Grav\Plugin
 */
class IPRedirectPlugin extends Plugin
{
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    public function onPluginsInitialized()
    {
        //don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        //hook into the page initialization event
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0]
        ]);
    }

    //run when page has initialized
    public function onPageInitialized()
    {
        //load the custom PHP for generating the lists
        require_once(__DIR__ . '/classes/geoplugin.class.php');
        require_once(__DIR__ . '/classes/freegeoip.class.php');
        require_once(__DIR__ . '/classes/ipinfo.class.php');

        //get the currently loaded route
        $route = $this->grav['page']->route();

        //if the currently loaded route is the same as the redirector_route in config
        if($route == $this->config->get('plugins.ipredirect.redirecter_path')) {

            //get the client ip or use the test_ip from the config
            $ip = $this->config->get('plugins.ipredirect.test_ip') ?? $_SERVER['REMOTE_ADDR'];

            //get the redirection array from config
            $redirects = $this->config->get('plugins.ipredirect.redirects');

            //iterate through the ip locater services and load the appropriate class
            foreach ($this->config->get('plugins.iplocate.sequence') as $service) {
                try {
                    if ($service === 'geoplugin') {
                        $locator = new geoPlugin();

                    } elseif ($service === 'freegeoip') {
                        $locator = new freeGeoIP();

                    } elseif ($service === 'ipinfo') {
                        $locator = new IPInfo();
                    }

                    //fetch ip country code
                    $locator->locate($this->grav['cache'], $ip);
                    $country_code = strtolower($locator->countryCode);

                    if($this->config->get('plugins.ipredirect.test_country_code')){
                        $country_code = $this->config->get('plugins.ipredirect.test_country_code');
                    }

                    //if the redirection is set up in the config, redirect to the corresponding path
                    if(isset($redirects[$country_code])){
                        $redirects[$country_code] = strtolower($redirects[$country_code]);
                        header('Location: ' . $redirects[$country_code]);
                        die;
                    }

                    //if no country code/path pair is found that matches the ip country code, just load the page (located at redirecter_path)

                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }
}
