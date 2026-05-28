<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Registers default Custom Certificate element types with the element registry.
 *
 * @package    mod_customcert
 * @copyright  2025 Mark Nelson <mdjnelson@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_customcert\element;

use mod_customcert\service\element_registry;
use mod_customcert\element\provider\plugin_provider;
use customcertelement_text\element as text_element;
use customcertelement_image\element as image_element;
use customcertelement_date\element as date_element;
use customcertelement_grade\element as grade_element;
use customcertelement_coursename\element as coursename_element;
use customcertelement_code\element as code_element;
use customcertelement_bgimage\element as bgimage_element;
use customcertelement_border\element as border_element;
use customcertelement_categoryname\element as categoryname_element;
use customcertelement_coursefield\element as coursefield_element;
use customcertelement_digitalsignature\element as digitalsignature_element;
use customcertelement_expiry\element as expiry_element;
use customcertelement_gradeitemname\element as gradeitemname_element;
use customcertelement_qrcode\element as qrcode_element;
use customcertelement_studentname\element as studentname_element;
use customcertelement_teachername\element as teachername_element;
use customcertelement_userfield\element as userfield_element;
use customcertelement_userpicture\element as userpicture_element;

/**
 * Bootstrap helper to register bundled and discovered element types.
 */
final class element_bootstrap {
    /**
     * Register the bundled element types to their existing classes.
     *
     * Note: This does not wire any runtime path; callers should invoke this
     * method explicitly (e.g., in tests or during controlled initialization).
     *
     * @param element_registry $registry Element registry to receive registrations.
     * @param plugin_provider|null $provider Optional provider for customcertelement plugin discovery.
     * @return void
     */
    public static function register_defaults(element_registry $registry, ?plugin_provider $provider = null): void {
        // Defensive load of bundled element class files. The Moodle autoloader
        // normally resolves these via core_component's subplugin map, but
        // environments that ship a pre-built component cache (e.g. Moodle
        // Playground) may have a stale map that does not yet include this
        // plugin's customcertelement_* subplugin type, causing class_exists()
        // to fail below. Loading the files directly is a no-op in healthy
        // environments and makes the bundled elements always available.
        self::load_bundled_element_files();

        // Core/bundled elements shipped with mod_customcert.
        $registry->register('text', text_element::class);
        $registry->register('image', image_element::class);
        $registry->register('date', date_element::class);
        $registry->register('grade', grade_element::class);
        $registry->register('coursename', coursename_element::class);
        $registry->register('code', code_element::class);
        $registry->register('bgimage', bgimage_element::class);
        $registry->register('border', border_element::class);
        $registry->register('categoryname', categoryname_element::class);
        $registry->register('coursefield', coursefield_element::class);
        $registry->register('digitalsignature', digitalsignature_element::class);
        $registry->register('expiry', expiry_element::class);
        $registry->register('gradeitemname', gradeitemname_element::class);
        $registry->register('qrcode', qrcode_element::class);
        $registry->register('studentname', studentname_element::class);
        $registry->register('teachername', teachername_element::class);
        $registry->register('userfield', userfield_element::class);
        $registry->register('userpicture', userpicture_element::class);

        // Auto-discover third-party customcertelement_* plugins and register them.
        // This preserves explicit core registrations while enabling ecosystem compatibility.
        // Discovery with simple in-request memoization to avoid repeated scanning.
        static $discovered = []; // Cache keyed by provider class name.
        try {
            $provider = $provider ?? new provider\core_plugin_provider();
            $cachekey = get_class($provider);
            if (!array_key_exists($cachekey, $discovered)) {
                $discovered[$cachekey] = [];
                $plugins = $provider->get_plugins();
                foreach ($plugins as $name => $unused) {
                    $type = (string)$name; // E.g., 'foobar' for customcertelement_foobar.
                    $classname = "\\customcertelement_{$type}\\element";
                    if (class_exists($classname)) {
                        $discovered[$cachekey][$type] = $classname;
                    } else if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                        $missingclass = "\\customcertelement_{$type}\\element";
                        debugging(
                            "Found plugin 'customcertelement_{$type}' but missing element class {$missingclass}.",
                            DEBUG_DEVELOPER
                        );
                    }
                }
            }
            // Register discovered classes that aren't already in the registry.
            foreach ($discovered[$cachekey] as $type => $classname) {
                if ($registry->has($type)) {
                    continue;
                }
                try {
                    $registry->register($type, $classname);
                } catch (\Throwable $e) {
                    debugging("Failed to register customcertelement '{$type}': {$e->getMessage()}", DEBUG_DEVELOPER);
                }
            }
        } catch (\Throwable $e) {
            if (!defined('PHPUNIT_TEST') && !defined('BEHAT_SITE_RUNNING')) {
                debugging('Element discovery failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Defensively require the class files for the bundled element subplugins.
     *
     * The customcertelement_* element types ship inside this plugin's source
     * tree at mod/customcert/element/<type>/classes/element.php. They are
     * normally autoloaded via core_component's subplugin map, but on
     * environments with a stale or pre-built component cache (e.g. Moodle
     * Playground) the autoloader may not know about them, which makes
     * class_exists() return false and breaks element_registry::register().
     * Requiring the files directly here guarantees the bundled classes are
     * always defined before they are registered.
     *
     * @return void
     */
    private static function load_bundled_element_files(): void {
        $bundled = [
            'text', 'image', 'date', 'grade', 'coursename', 'code', 'bgimage',
            'border', 'categoryname', 'coursefield', 'digitalsignature',
            'expiry', 'gradeitemname', 'qrcode', 'studentname', 'teachername',
            'userfield', 'userpicture',
        ];
        $base = __DIR__ . '/../../element';
        foreach ($bundled as $type) {
            $file = $base . '/' . $type . '/classes/element.php';
            if (is_readable($file)) {
                require_once($file);
            }
        }
    }
}
