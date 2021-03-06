<?php
/**
 * Copyright (C) 2019 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - http://www.xibo.org.uk
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Xibo\Tests\integration\Cache;

use Xibo\Entity\Display;
use Xibo\OAuth2\Client\Entity\XiboDisplay;
use Xibo\OAuth2\Client\Entity\XiboImage;
use Xibo\OAuth2\Client\Entity\XiboLayout;
use Xibo\OAuth2\Client\Entity\XiboLibrary;
use Xibo\OAuth2\Client\Entity\XiboPlaylist;
use Xibo\OAuth2\Client\Entity\XiboRegion;
use Xibo\OAuth2\Client\Entity\XiboSchedule;
use Xibo\OAuth2\Client\Entity\XiboText;
use Xibo\OAuth2\Client\Entity\XiboTicker;
use Xibo\OAuth2\Client\Entity\XiboVideo;
use Xibo\Tests\Helper\DisplayHelperTrait;
use Xibo\Tests\Helper\LayoutHelperTrait;
use Xibo\Tests\LocalWebTestCase;
use Xibo\Helper\Random;

/**
 * Class LayoutProofOfPlayXMLMediaInheritWidgetOffTest
 * @package Xibo\Tests\integration\Cache
 */
class LayoutProofOfPlayXMLMediaInheritWidgetOffTest extends LocalWebTestCase
{
    use LayoutHelperTrait;
    use DisplayHelperTrait;

    /** @var XiboLayout */
    protected $layoutOff;

    /** @var XiboLayout */
    protected $layoutOn;

    /** @var XiboRegion */
    protected $region;

    /** @var XiboDisplay */
    protected $display;

    /** @var XiboDisplay */
    protected $display2;

    /** @var XiboTicker */
    protected $widget;

    /** @var XiboLibrary */
    protected $media;

    /** @var XiboLibrary */
    protected $mediaOn;

    protected $widgetId;
    protected $widgetId2;

    // <editor-fold desc="Init">
    public function setup()
    {
        parent::setup();

        $this->getLogger()->debug('Setup test for ' . get_class() .' Test');

        // Set global widget enable stat set to Off
        self::$container->configService->changeSetting('WIDGET_STATS_ENABLED_DEFAULT', 'Off');
        $this->getStore()->commitIfNecessary();

        // Create a Layout with enableStat Off (by default)
        $this->layoutOff = $this->createLayout();
        $layoutOff = $this->checkout($this->layoutOff);

        // Upload some media - enableStat is Inherit (from global media stat setting)
        $this->media = (new XiboLibrary($this->getEntityProvider()))->create(
            Random::generateString(8, 'API Video'),
            PROJECT_ROOT . '/tests/resources/HLH264.mp4'
        );

        // Assign the media we've edited to our regions playlist- widget with Inherit (from global widget stat setting)
        $playlist = (new XiboPlaylist($this->getEntityProvider()))
            ->assign([$this->media->mediaId], 10, $layoutOff->regions[0]->regionPlaylist->playlistId);

        // Store the widgetId
        $this->widgetId = $playlist->widgets[0]->widgetId;

        // Create a Display
        $this->display = $this->createDisplay();

        // Schedule the Layout "always" onto our display
        //  deleting the layout will remove this at the end
        $event = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()+3600),
            date('Y-m-d H:i:s', time()+7200),
            $this->layoutOff->campaignId,
            [$this->display->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );

        $this->displaySetStatus($this->display, Display::$STATUS_DONE);
        $this->displaySetLicensed($this->display);

        // Create a layout with enableStat On
        $this->layoutOn = (new XiboLayout($this->getEntityProvider()))->create(
            Random::generateString(8, 'phpunit'),
            'phpunit description',
            '',
            $this->getResolutionId('landscape'),
            1
        );
        $layoutOn = $this->checkout($this->layoutOn);

        // Assign the media we've created to our regions playlist- widget with Inherit (from global widget stat setting)
        $playlist2 = (new XiboPlaylist($this->getEntityProvider()))
            ->assign([$this->media->mediaId], 10, $layoutOn->regions[0]->regionPlaylist->playlistId);

        // Store the widgetId
        $this->widgetId2 = $playlist2->widgets[0]->widgetId;

        // Create a Display2
        $this->display2 = $this->createDisplay();

        // Schedule the LayoutOn "always" onto our display
        //  deleting the layoutOn will remove this at the end
        $event2 = (new XiboSchedule($this->getEntityProvider()))->createEventLayout(
            date('Y-m-d H:i:s', time()+3600),
            date('Y-m-d H:i:s', time()+7200),
            $this->layoutOn->campaignId,
            [$this->display2->displayGroupId],
            0,
            NULL,
            NULL,
            NULL,
            0,
            0,
            0
        );

        $this->displaySetStatus($this->display2, Display::$STATUS_DONE);
        $this->displaySetLicensed($this->display2);

        $this->getLogger()->debug('Finished Setup');
    }

    public function tearDown()
    {
        $this->getLogger()->debug('Tear Down');

        parent::tearDown();

        // Delete the LayoutOn
        $this->deleteLayout($this->layoutOff);

        // Delete the Display2
        $this->deleteDisplay($this->display);

        // Delete the LayoutOn
        $this->deleteLayout($this->layoutOn);

        // Delete the Display2
        $this->deleteDisplay($this->display2);

        // Delete the media record
        $this->media->deleteAssigned();

        // Set global widget enable stat set to Inherit
        self::$container->configService->changeSetting('WIDGET_STATS_ENABLED_DEFAULT', 'Inherit');
        $this->getStore()->commitIfNecessary();

    }
    // </editor-fold>

//                Logic Table
//
//                Widget With Media
//                LAYOUT	MEDIA	WIDGET	Media stats collected?
//                    ON	ON	    ON	    YES     Widget takes precedence     // Match - 1
//                    ON	OFF	    ON	    YES     Widget takes precedence     // Match - 1
//                    ON	INHERIT	ON	    YES     Widget takes precedence     // Match - 1
//
//                    OFF	ON	    ON	    YES     Widget takes precedence     // Match - 1
//                    OFF	OFF	    ON	    YES     Widget takes precedence     // Match - 1
//                    OFF	INHERIT	ON	    YES     Widget takes precedence     // Match - 1
//
//                    ON	ON	    OFF	    NO      Widget takes precedence     // Match - 2
//                    ON	OFF	    OFF	    NO      Widget takes precedence     // Match - 2
//                    ON	INHERIT	OFF	    NO      Widget takes precedence     // Match - 2
//
//                    OFF	ON	    OFF	    NO      Widget takes precedence     // Match - 2
//                    OFF	OFF	    OFF	    NO      Widget takes precedence     // Match - 2
//                    OFF	INHERIT	OFF	    NO      Widget takes precedence     // Match - 2
//
//                    ON	ON	    INHERIT	YES     Media takes precedence      // Match - 3
//                    ON	OFF	    INHERIT	NO      Media takes precedence      // Match - 4
//                    ON	INHERIT	INHERIT	YES     Media takes precedence and Inherited from Layout        // Match - 5
//
//                    OFF	ON	    INHERIT	YES     Media takes precedence      // Match - 3
//                    OFF	OFF	    INHERIT	NO      Media takes precedence      // Match - 4
//                    OFF	INHERIT	INHERIT	NO      Media takes precedence and Inherited from Layout        // Match - 6
////

    public function testLayoutOff()
    {
        // Publish layout
        $response = $this->client->put('/layout/publish/' . $this->layoutOff->layoutId);
        $response = json_decode($response, true);

        $this->layoutOff = $this->constructLayoutFromResponse($response['data']);

        // Confirm our Layout is in the Schedule
        $schedule = $this->getXmdsWrapper()->Schedule($this->display->license);
        $this->assertContains('file="' . $this->layoutOff->layoutId . '"', $schedule, 'Layout not scheduled');

        // Call Required Files
        $rf = $this->getXmdsWrapper()->RequiredFiles($this->display->license);

        // Get XML string for player
        $xmlString = $this->getXmdsWrapper()->GetFile($this->display->license, $this->layoutOff->layoutId, 'layout', 0, 0);

        // Layout enable stat 0
        $this->assertContains('<layout width="1920" height="1080" bgcolor="#000" schemaVersion="3" enableStat="0">', $xmlString );

        // Layout Off, Media Inherit, Widget Off, Output => [0, 'Inherit', 'Off', 0],
        $this->assertContains('<media id="'.$this->widgetId.'" type="video" render="native" duration="0" useDuration="1" fromDt="1970-01-01 01:00:00" toDt="2038-01-19 03:14:07" enableStat="0" fileId="'.$this->media->mediaId.'">', $xmlString );

    }

    public function testLayoutOn()
    {

        // Publish layout
        $response = $this->client->put('/layout/publish/' . $this->layoutOn->layoutId);
        $response = json_decode($response, true);

        $this->layoutOn = $this->constructLayoutFromResponse($response['data']);

        // Confirm our Layout is in the Schedule
        $schedule = $this->getXmdsWrapper()->Schedule($this->display2->license);
        $this->assertContains('file="' . $this->layoutOn->layoutId . '"', $schedule, 'Layout not scheduled');

        // Call Required Files
        $rf = $this->getXmdsWrapper()->RequiredFiles($this->display2->license);

        // Get XML string for player
        $xmlString = $this->getXmdsWrapper()->GetFile($this->display2->license, $this->layoutOn->layoutId, 'layout', 0, 0);

        // Layout enable stat 1
        $this->assertContains('<layout width="1920" height="1080" bgcolor="#000" schemaVersion="3" enableStat="1">', $xmlString );

        // Layout On, Media Inherit, Widget Off, Output => [1, 'Off', 'Off', 0],
        $this->assertContains('<media id="'.$this->widgetId2.'" type="video" render="native" duration="0" useDuration="1" fromDt="1970-01-01 01:00:00" toDt="2038-01-19 03:14:07" enableStat="0" fileId="'.$this->media->mediaId.'">', $xmlString );

    }

}