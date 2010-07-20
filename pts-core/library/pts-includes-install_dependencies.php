<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008 - 2010, Phoronix Media
	Copyright (C) 2008 - 2010, Michael Larabel
	pts-includes-install_dependencies.php: Functions needed for installing external dependencies for PTS.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

function pts_install_package_on_distribution(&$display_mode, &$identifiers, &$tests_with_satisfied_dependencies)
{
	// PTS External Dependencies install on distribution
	$tests_checked = array();
	$install_objects = array();
	$install_objects_count = 0;

	if(IS_WINDOWS || pts_client::read_env("SKIP_EXTERNAL_DEPENDENCIES_CHECK"))
	{
		// Windows doesn't use any external dependencies
		return true;
	}

	foreach(pts_arrays::to_array($identifiers) as $identifier)
	{
		foreach(pts_contained_tests($identifier, true) as $test)
		{
			if(pts_test_supported($test) && !in_array($test, $tests_checked))
			{
				pts_install_external_dependencies_list($test, $install_objects);

				// Calculate tests that have all dependencies satisfied, if needs to selectively install these tests
				// This is particularly useful when running in automated mode where below if user is not root returns false
				if(($c = count($install_objects)) == $install_objects_count)
				{
					array_push($tests_with_satisfied_dependencies, $test);
				}
				else
				{
					$install_objects_count = $c;
				}

				array_push($tests_checked, $test);
			}
		}
	}

	$install_objects = array_unique($install_objects);

	if(pts_is_assignment("AUTOMATED_MODE") && phodevi::read_property("system", "username") != "root")
	{
		return count($install_objects) == 0;
	}

	pts_install_packages_on_distribution_process($install_objects, $display_mode);

	return true;
}
function pts_external_dependency_generic_title($generic_name)
{
	// Get the generic information for a PTS External Dependency generic
	$generic_title = null;

	$xml_parser = new pts_external_dependencies_tandem_XmlReader(STATIC_DIR . "distro-xml/generic-packages.xml");
	$package_name = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_GENERIC);
	$title = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_TITLE);

	foreach(array_keys($package_name) as $i)
	{
		if($generic_name == $package_name[$i])
		{
			$generic_title = $title[$i];
			break;
		}
	}

	return $generic_title;
}
function pts_external_dependency_generic_info($missing_dependency_names)
{
	// Get the generic information for a PTS External Dependency generic
	$missing_count = 0;
	$xml_parser = new pts_external_dependencies_tandem_XmlReader(STATIC_DIR . "distro-xml/generic-packages.xml");
	$package_name = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_GENERIC);
	$title = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_TITLE);
	$possible_packages = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_POSSIBLENAMES);
	$file_check = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_FILECHECK);

	foreach(array_keys($title) as $i)
	{
		if(in_array($package_name[$i], $missing_dependency_names))
		{
			if(pts_file_missing_check($file_check[$i]))
			{
				echo pts_string_header($title[$i] . "\nPossible Package Names: " . $possible_packages[$i]);
				$missing_count++;
			}
		}
	}

	return $missing_count;
}
function pts_external_dependency_generic_packages()
{
	$xml_parser = new pts_external_dependencies_tandem_XmlReader(STATIC_DIR . "distro-xml/generic-packages.xml");
	$packages = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_GENERIC);

	return $packages;
}
function pts_install_external_dependencies_list($identifier, &$install_objects)
{
	// Install from a list of external dependencies
	$dependencies = pts_test_read_xml($identifier, P_TEST_EXDEP);

	if(!empty($dependencies))
	{
		$dependencies = pts_strings::trim_explode(',', $dependencies);

		if(!pts_package_generic_to_distro_name($install_objects, $dependencies))
		{
			if(count($dependencies) > 0)
			{
				echo "\nSome additional dependencies are required, and they could not be installed automatically for your operating system.\nBelow are the software packages that must be installed for the test(s) to run properly.\n\n";
				$missing_count = pts_external_dependency_generic_info($dependencies);

				if($missing_count > 0)
				{
					echo "The above dependencies should be installed before proceeding. Press any key when you're ready to continue.";

					if(!pts_read_assignment("IS_BATCH_MODE") && !pts_is_assignment("AUTOMATED_MODE"))
					{		
						pts_user_io::read_user_input();
					}
				}
			}
		}
	}
}
function pts_package_generic_to_distro_name(&$package_install_array, $generic_names, $write_generic_name = false)
{
	// Generic name to distribution package name
	$vendor = pts_package_vendor_identifier();
	$generated = false;

	if(is_file(STATIC_DIR . "distro-xml/" . $vendor . "-packages.xml"))
	{
		$xml_parser = new pts_external_dependencies_tandem_XmlReader(STATIC_DIR . "distro-xml/" . $vendor . "-packages.xml");
		$generic_package = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_GENERIC);
		$distro_package = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_SPECIFIC);
		$file_check = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_FILECHECK);
		$arch_specific = $xml_parser->getXMLArrayValues(P_EXDEP_PACKAGE_ARCHSPECIFIC);
		$kernel_architecture = phodevi::read_property("system", "kernel-architecture");

		foreach(array_keys($generic_package) as $i)
		{
			if(!empty($generic_package[$i]) && ($generic_names == "all" || in_array($generic_package[$i], $generic_names)))
			{
				if(!in_array($distro_package[$i], $package_install_array))
				{
					$add_dependency = empty($file_check[$i]) || pts_file_missing_check($file_check[$i]);
					$arch_compliant = empty($arch_specific[$i]) || in_array($kernel_architecture, pts_strings::trim_explode(',', $arch_specific[$i]));

					if($add_dependency && $arch_compliant)
					{
						array_push($package_install_array, ($write_generic_name ? $generic_package[$i] : $distro_package[$i]));
					}
				}
			}
		}
		$generated = true;
	}

	return $generated;
}
function pts_external_dependencies_installed()
{
	$missing_dependencies = pts_external_dependencies_missing();
	$installed_dependencies = array();

	foreach(pts_external_dependency_generic_packages() as $package)
	{
		if(!in_array($package, $missing_dependencies))
		{
			array_push($installed_dependencies, $package);
		}
	}

	return $installed_dependencies;	
}
function pts_external_dependencies_missing()
{
	$missing_dependencies = array();
	pts_package_generic_to_distro_name($missing_dependencies, "all", true);

	return $missing_dependencies;	
}
function pts_install_packages_on_distribution_process($install_objects, &$display_mode)
{
	// Do the actual installing process of packages using the distribution's package management system
	if(!empty($install_objects))
	{
		if(is_array($install_objects))
		{
			$install_objects = implode(" ", $install_objects);
		}

		$distribution = pts_package_vendor_identifier();

		if(is_file(STATIC_DIR . "distro-scripts/install-" . $distribution . "-packages.sh"))
		{
			// hook into $display_mode here if it's desired
			echo "\nThe following dependencies are needed and will be installed: \n\n";
			echo pts_user_io::display_text_list(explode(' ', $install_objects));
			echo "\nThis process may take several minutes.\n";

			echo shell_exec("cd " . STATIC_DIR . "distro-scripts/ && sh install-" . $distribution . "-packages.sh " . $install_objects);
		}
		else
		{
			if(!IS_MACOSX)
			{
				echo "Distribution install script not found!";
			}
		}
	}
	else if(pts_read_assignment("COMMAND") == "install_dependencies")
	{
		echo "\nThere are no external dependencies to install.\n\n";
	}
}
function pts_file_missing_check($file_arr)
{
	// Checks if file is missing
	$file_missing = false;

	if(!is_array($file_arr))
	{
		$file_arr = explode(',', $file_arr);
	}

	foreach($file_arr as $file)
	{
		$file_is_there = false;
		$file = explode("OR", $file);

		for($i = 0; $i < count($file) && $file_is_there == false; $i++)
		{
			$file[$i] = trim($file[$i]);

			if(is_file($file[$i]) || is_dir($file[$i]) || is_link($file[$i]))
			{
				$file_is_there = true;
			}
		}
		$file_missing = $file_missing || !$file_is_there;
	}

	return $file_missing;
}
function pts_package_vendor_identifier()
{
	$os_vendor = phodevi::read_property("system", "vendor-identifier");

	if(!is_file(STATIC_DIR . "distro-xml/" . $os_vendor . "-packages.xml") && !is_file(STATIC_DIR . "distro-scripts/install-" . $os_vendor . "-packages.sh"))
	{
		$vendors_alias_file = pts_file_get_contents(STATIC_DIR . "lists/software-vendor-aliases.list");
		$vendors_r = explode("\n", $vendors_alias_file);

		foreach($vendors_r as &$vendor)
		{
			$vendor_r = pts_strings::trim_explode("=", $vendor);

			if(count($vendor_r) == 2)
			{
				if($os_vendor == $vendor_r[0])
				{
					$os_vendor = $vendor_r[1];
					break;
				}
			}
		}
	}

	return $os_vendor;
}

?>
