
.. code-block:: php

    <?php

/*
 * Este archivo es parte de la plataforma Silex.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * Para información completa sobre los derechos de autor y licencia, por
 * favor, ve el archivo LICENSE que viene con este código fuente.
 */

namespace Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Doctrine\Common\EventManager;

/**
 * Doctrine DBAL Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DoctrineServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['db.default_options'] = array(
            'driver'   => 'pdo_mysql',
            'dbname'   => null,
            'host'     => 'localhost',
            'user'     => 'root',
            'password' => null,
        );

        $app['dbs.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;

            if ($initialized) {
                return;
            }

            $initialized = true;

            if (!isset($app['dbs.options'])) {
                $app['dbs.options'] = array('default' => isset($app['db.options']) ? $app['db.options'] : array());
            }

            $tmp = $app['dbs.options'];
            foreach ($tmp as $name => &$options) {
                $options = array_replace($app['db.default_options'], $options);

                if (!isset($app['dbs.default'])) {
                    $app['dbs.default'] = $name;
                }
            }
            $app['dbs.options'] = $tmp;
        });

        $app['dbs'] = $app->share(function () use ($app) {
            $app['dbs.options.initializer']();

            $dbs = new \Pimple();
            foreach ($app['dbs.options'] as $name => $options) {
                if ($app['dbs.default'] === $name) {
                    // we use shortcuts here in case the default has been overriden
                    $config = $app['db.config'];
                    $manager = $app['db.event_manager'];
                } else {
                    $config = $app['dbs.config'][$name];
                    $manager = $app['dbs.event_manager'][$name];
                }

                $dbs[$name] = DriverManager::getConnection($options, $config, $manager);
            }

            return $dbs;
        });

        $app['dbs.config'] = $app->share(function () use ($app) {
            $app['dbs.options.initializer']();

            $configs = new \Pimple();
            foreach ($app['dbs.options'] as $name => $options) {
                $configs[$name] = new Configuration();
            }

            return $configs;
        });

        $app['dbs.event_manager'] = $app->share(function () use ($app) {
            $app['dbs.options.initializer']();

            $managers = new \Pimple();
            foreach ($app['dbs.options'] as $name => $options) {
                $managers[$name] = new EventManager();
            }

            return $managers;
        });

        // shortcuts for the "first" DB
        $app['db'] = $app->share(function() use ($app) {
            $dbs = $app['dbs'];

            return $dbs[$app['dbs.default']];
        });

        $app['db.config'] = $app->share(function() use ($app) {
            $dbs = $app['dbs.config'];

            return $dbs[$app['dbs.default']];
        });

        $app['db.event_manager'] = $app->share(function() use ($app) {
            $dbs = $app['dbs.event_manager'];

            return $dbs[$app['dbs.default']];
        });
    }

    public function boot(Application $app)
    {
    }
}