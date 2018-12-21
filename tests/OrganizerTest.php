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
    public function it_should_()
    {
        // Arrange

        // Act
        $result = (new Organizer())->execute();

        // Assert
        $this->assertNull($result);
    }
}