<?php

declare(strict_types=1);

namespace Brzuchal\SchedulerBundle\Tests;

use Brzuchal\SchedulerBundle\SchedulerBundle;
use Brzuchal\SchedulerBundle\Tests\Fixtures\FooMessageHandler;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

use function sprintf;

final class TestKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    protected const DEFAULT_SETUP_CONFIG = [
        'store' => [
            'driver' => 'doctrine',
            'connection' => 'default',
        ],
    ];
    /** @psalm-var array<array-key, mixed> */
    protected array $setupConfig = self::DEFAULT_SETUP_CONFIG;

    /** @return iterable<BundleInterface> */
    public function registerBundles(): iterable
    {
        return [
            new DoctrineBundle(),
            new FrameworkBundle(),
            new SchedulerBundle(),
        ];
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/var/cache', $this->getProjectDir());
    }

    public function getLogDir(): string
    {
        return sprintf('%s/var/logs', $this->getProjectDir());
    }

    public function process(ContainerBuilder $container): void
    {
        $this->expose($container, 'scheduler.store');
        $this->expose($container, 'scheduler.executor');
        $this->expose($container, 'scheduler');
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'sqlite',
                'charset' => 'utf8mb4',
                'url' => 'sqlite:///data-symfony.sqlite',
                'default_table_options' => [
                    'charset' => 'utf8mb4',
                    'utf8mb4_unicode_ci' => 'utf8mb4_unicode_ci',
                ],
            ],
        ]);

        $container->loadFromExtension('framework', [
            'secret' => 'nope',
            'test' => true,
            'messenger' => [
                'transports' => ['test' => 'in-memory:///'],
            ],
        ]);

        $container->loadFromExtension('scheduler', $this->setupConfig);
        $container->setDefinition('test.foo_handler', new Definition(FooMessageHandler::class))
            ->addTag('messenger.message_handler');
    }

    private function expose(ContainerBuilder $container, string $serviceId): void
    {
        if ($container->hasDefinition($serviceId)) {
            $container->getDefinition($serviceId)->setPublic(true);
        }

        if (! $container->hasAlias($serviceId)) {
            return;
        }

        $container->getAlias($serviceId)->setPublic(true);
    }
}
