<?php declare(strict_types=1);

namespace Base3IliasLab;

use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3IliasLab\DataHawk\IliasQuerySchemaProvider;
use Base3IliasLab\Vizion\IliasReportConfigProvider;
use ResourceFoundation\Api\IQuerySchemaProvider;
use Vizion\Api\IReportConfigProvider;

class Base3IliasLabPlugin implements IPlugin {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'base3iliaslabplugin';
	}

	// Implementation of IPlugin

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED)
                        ->set(IQuerySchemaProvider::class, fn($c) => new IliasQuerySchemaProvider, IContainer::SHARED)
			->set(IReportConfigProvider::class, fn() => new IliasReportConfigProvider, IContainer::SHARED);
	}
}
