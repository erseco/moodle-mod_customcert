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

declare(strict_types=1);

namespace mod_customcert;

use advanced_testcase;
use mod_customcert\element\element_bootstrap;
use mod_customcert\service\element_registry;
use ReflectionClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests the defensive load of bundled element class files in element_bootstrap.
 *
 * @package    mod_customcert
 * @copyright  2026 Ernesto Serrano <info@ernesto.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_customcert\element\element_bootstrap
 */
final class element_bootstrap_bundled_files_test extends advanced_testcase {

    /**
     * Every entry in the hardcoded bundled list must point to a real class file
     * shipped inside this plugin's element/<type>/classes/element.php tree.
     *
     * This guards against drift: if a bundled element is renamed or removed
     * without updating load_bundled_element_files(), the require_once would
     * silently miss it on stale-cache environments.
     */
    public function test_bundled_list_matches_shipped_element_files(): void {
        $reflection = new ReflectionClass(element_bootstrap::class);
        $method = $reflection->getMethod('load_bundled_element_files');
        $source = file_get_contents($reflection->getFileName());
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        $body = implode("\n", array_slice(explode("\n", $source), $start - 1, $end - $start + 1));

        preg_match("/\\\$bundled\\s*=\\s*\\[(.*?)\\];/s", $body, $matches);
        $this->assertNotEmpty($matches, 'Could not locate $bundled array in load_bundled_element_files().');

        preg_match_all("/'([a-z]+)'/", $matches[1], $names);
        $bundled = $names[1];
        $this->assertNotEmpty($bundled, 'Bundled element list is empty.');

        $base = __DIR__ . '/../element';
        foreach ($bundled as $type) {
            $file = $base . '/' . $type . '/classes/element.php';
            $this->assertFileExists($file, "Bundled element '$type' has no class file at $file.");
        }
    }

    /**
     * After register_defaults() runs, every bundled customcertelement_*\element
     * class must be loaded. This is the contract that protects environments
     * with a stale core_component cache.
     */
    public function test_register_defaults_loads_all_bundled_element_classes(): void {
        $this->resetAfterTest();

        $registry = new element_registry();
        element_bootstrap::register_defaults($registry);

        $reflection = new ReflectionClass(element_bootstrap::class);
        $source = file_get_contents($reflection->getFileName());
        preg_match("/\\\$bundled\\s*=\\s*\\[(.*?)\\];/s", $source, $matches);
        preg_match_all("/'([a-z]+)'/", $matches[1], $names);

        foreach ($names[1] as $type) {
            $class = 'customcertelement_' . $type . '\\element';
            $this->assertTrue(
                class_exists($class, false),
                "Bundled element class $class was not loaded after register_defaults()."
            );
            $this->assertTrue($registry->has($type), "Bundled element '$type' is not in the registry.");
        }
    }
}
