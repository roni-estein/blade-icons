<?php

declare(strict_types=1);

namespace Tests;

use BladeUI\Icons\BladeIconsServiceProvider;
use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory;
use BladeUI\Icons\Svg;
use Illuminate\Filesystem\Filesystem;
use Mockery;

class FactoryTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();

        Mockery::close();
    }

    /** @test */
    public function it_can_add_a_set()
    {
        $factory = $this->prepareSets();

        $sets = $factory->all();

        $this->assertCount(2, $sets);
    }

    /** @test */
    public function it_can_get_all_icons_in_a_set()
    {
        $set = 'default';
        $options = [
            'path' => __DIR__ . '/resources/svg',
            'prefix' => 'icon',
            'class' => '',
        ];

        $factory = (new Factory(new Filesystem(), ''))
            ->add($set, $options);

        $files = $factory->getFiles($set, $options);

        $this->assertCount(4, $files);
    }

    /** @test */
    public function it_can_get_only_filtered_icons_in_a_set()
    {
        $factory = $this->prepareSets();

        $factory->addFilters([
            'default' => [
                'zondicon-flag',
                'solid.camera'
            ]
        ]);

        $this->assertCount(2, $factory->getFiles('default'));
    }

    /** @test */
    public function it_throws_an_exception_when_filtered_icon_is_not_found()
    {
        $factory = $this->prepareSets();

        $factory->addFilters([
            'default' => [
                'money'
            ],
        ]);

        $this->expectException(SvgNotFound::class);
        $this->expectExceptionMessage('Svg by name "money" from set "default" not found.');

        $factory->getFiles('default');
    }

    /** @test */
    public function it_can_retrieve_an_icon()
    {
        $factory = $this->prepareSets();

        $icon = $factory->svg('camera');

        $this->assertInstanceOf(Svg::class, $icon);
        $this->assertSame('camera', $icon->name());
    }

    /** @test */
    public function it_can_retrieve_an_icon_with_default_prefix()
    {
        $factory = $this->prepareSets();

        $icon = $factory->svg('icon-camera');

        $this->assertInstanceOf(Svg::class, $icon);
        $this->assertSame('camera', $icon->name());
    }

    /** @test */
    public function it_can_retrieve_an_icon_with_a_dash()
    {
        $factory = $this->prepareSets();

        $icon = $factory->svg('foo-camera');

        $this->assertInstanceOf(Svg::class, $icon);
        $this->assertSame('foo-camera', $icon->name());
    }

    /** @test */
    public function it_can_retrieve_an_icon_from_a_specific_set()
    {
        $factory = $this->prepareSets();

        $icon = $factory->svg('zondicon-flag');

        $this->assertInstanceOf(Svg::class, $icon);
        $this->assertSame('flag', $icon->name());
    }

    /** @test */
    public function icons_from_sets_other_than_default_are_retrieved_first()
    {
        $factory = $this->prepareSets();

        $icon = $factory->svg('zondicon-flag');

        $expected = <<<HTML
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M7.667 12H2v8H0V0h12l.333 2H20l-3 6 3 6H8l-.333-2z"/></svg>
HTML;

        $this->assertSame($expected, $icon->contents());
    }

    /** @test */
    public function icons_are_cached()
    {
        $filesystem = Mockery::mock(Filesystem::class);
        $filesystem->shouldReceive('missing')->andReturn(false);
        $filesystem->shouldReceive('get')
            ->once()
            ->with('/default/svg/camera.svg')
            ->andReturn($defaultIcon = '<svg>Foo</svg>');
        $filesystem->shouldReceive('get')
            ->once()
            ->with('/heroicon/svg/camera.svg')
            ->andReturn($heroicon = '<svg>Bar</svg>');

        $factory = new Factory($filesystem);

        $factory->add('default', [
            'path' => '/default/svg',
            'prefix' => 'default',
        ]);
        $factory->add('heroicon', [
            'path' => '/heroicon/svg',
            'prefix' => 'heroicon',
        ]);

        $this->assertSame($defaultIcon, $factory->svg('camera')->contents());
        $this->assertSame($defaultIcon, $factory->svg('camera')->contents());
        $this->assertSame($heroicon, $factory->svg('heroicon-camera')->contents());
        $this->assertSame($heroicon, $factory->svg('heroicon-camera')->contents());
    }

    /** @test */
    public function default_icon_set_is_optional()
    {
        $factory = (new Factory(new Filesystem(), 'icon icon-default'))
            ->add('zondicons', [
                'path' => __DIR__ . '/resources/zondicons',
                'prefix' => 'zondicon',
                'class' => 'zondicon-class',
            ]);

        $factory = $this->app->instance(Factory::class, $factory);

        $icon = $factory->svg('zondicon-flag');

        $this->assertSame('icon icon-default zondicon-class', $icon->attributes()['class']);
    }

    /** @test */
    public function icon_not_found_without_default_set_throws_proper_exception()
    {
        $factory = (new Factory(new Filesystem(), 'icon icon-default'))
            ->add('zondicons', [
                'path' => __DIR__ . '/resources/zondicons',
                'prefix' => 'zondicon',
                'class' => 'zondicon-class',
            ]);

        $factory = $this->app->instance(Factory::class, $factory);

        $this->expectExceptionObject(new SvgNotFound(
            'Svg by name "foo" from set "zondicons" not found.'
        ));

        $factory->svg('zondicon-foo');
    }

    /** @test */
    public function icons_can_have_default_classes()
    {
        $factory = $this->prepareSets('icon icon-default');

        $icon = $factory->svg('camera', 'custom-class');

        $this->assertSame('icon icon-default custom-class', $icon->attributes()['class']);
    }

    /** @test */
    public function default_classes_are_always_applied()
    {
        $factory = $this->prepareSets('icon icon-default');

        $icon = $factory->svg('camera');

        $this->assertSame('icon icon-default', $icon->attributes()['class']);
    }

    /** @test */
    public function icons_can_have_default_classes_from_sets()
    {
        $factory = $this->prepareSets('icon icon-default', ['zondicons' => 'zondicon-class']);

        $icon = $factory->svg('camera');

        $this->assertSame('icon icon-default', $icon->attributes()['class']);

        $icon = $factory->svg('zondicon-flag');

        $this->assertSame('icon icon-default zondicon-class', $icon->attributes()['class']);
    }

    /** @test */
    public function default_classes_from_sets_are_applied_even_when_main_default_class_is_empty()
    {
        $factory = $this->prepareSets('', ['zondicons' => 'zondicon-class']);

        $icon = $factory->svg('camera');

        $this->assertArrayNotHasKey('class', $icon->attributes());

        $icon = $factory->svg('zondicon-flag');

        $this->assertSame('zondicon-class', $icon->attributes()['class']);
    }

    /** @test */
    public function passing_classes_as_attributes_will_override_default_classes()
    {
        $factory = $this->prepareSets('icon icon-default');

        $icon = $factory->svg('camera', '', ['class' => 'custom-class']);

        $this->assertSame('custom-class', $icon->attributes()['class']);

        $icon = $factory->svg('camera', ['class' => 'custom-class']);

        $this->assertSame('custom-class', $icon->attributes()['class']);
    }

    /** @test */
    public function icons_can_have_attributes()
    {
        $factory = $this->prepareSets('icon icon-default');

        $icon = $factory->svg('camera', ['style' => 'color: #fff']);

        $this->assertSame(['style' => 'color: #fff', 'class' => 'icon icon-default'], $icon->attributes());
    }

    /** @test */
    public function it_can_retrieve_an_icon_from_a_subdirectory()
    {
        $factory = $this->prepareSets();

        $icon = $factory->svg('solid.camera');

        $this->assertInstanceOf(Svg::class, $icon);
        $this->assertSame('solid.camera', $icon->name());
    }

    /** @test */
    public function it_throws_an_exception_when_no_icon_is_found()
    {
        $factory = $this->prepareSets();

        $this->expectException(SvgNotFound::class);
        $this->expectExceptionMessage('Svg by name "money" from set "default" not found.');

        $factory->svg('money');
    }

    protected function getPackageProviders($app): array
    {
        return [BladeIconsServiceProvider::class];
    }
}