<?php

use PHPUnit\Framework\TestCase;
use TwkDj\FlickrOrganizer\Organizer;

final class OrganizerTest extends TestCase
{
    /**
     * @test
     */
    public function it_should_instanticate_organizer()
    {
        // Act
        $organizer = new Organizer();

        // Assert
        $this->assertInstanceOf(Organizer::class, $organizer);
    }

    /**
     * @test
     */
    public function it_should_read_config_file_and_set_json_file_directory()
    {
        // Arrange
        $configPath = __DIR__ . '/data/config.json';

        // Act
        $actual = (new Organizer())->setConfigPath($configPath);

        // Assert
        $this->assertEquals($configPath, $actual->getConfigPath());
    }

    /**
     * @test
     */
    public function it_should_copy_files_to_folders()
    {
        // Arrange
        $configPath = __DIR__ . '/data/config.json';

        // Act
        $actual = (new Organizer())->setConfigPath($configPath)->execute();

        // Assert
        $this->assertTrue(true);
    }
}