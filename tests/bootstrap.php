<?php

/**
 * PHPUnit bootstrap file for FusionDirectory tests.
 *
 * This bootstraps the minimal autoloading needed for unit tests
 * without requiring a full LDAP/Smarty/web environment.
 */

// Register a minimal autoloader for the project's classes
spl_autoload_register(function (string $class): void {
    // Strip namespace prefix for attribute classes
    $relativeClass = preg_replace('/^FusionDirectory\\\\Core\\\\SimplePlugin\\\\Attribute$/', 'Attribute', $class);
    $relativeClass = preg_replace('/^FusionDirectory\\\\Core\\\\SimplePlugin\\\\/', '', $relativeClass);

    // Map class names to file paths
    $map = [
        'Attribute'                     => __DIR__ . '/../include/simpleplugin/class_Attribute.inc',
        'StringAttribute'               => __DIR__ . '/../include/simpleplugin/attributes/class_StringAttribute.inc',
        'TextAreaAttribute'             => __DIR__ . '/../include/simpleplugin/attributes/class_StringAttribute.inc',
        'PostalAddressAttribute'        => __DIR__ . '/../include/simpleplugin/attributes/class_PostalAddressAttribute.inc',
        'BaseSelectorAttribute'         => __DIR__ . '/../include/simpleplugin/attributes/class_BaseSelectorAttribute.inc',
        'BooleanAttribute'              => __DIR__ . '/../include/simpleplugin/attributes/class_BooleanAttribute.inc',
        'SelectAttribute'               => __DIR__ . '/../include/simpleplugin/attributes/class_SelectAttribute.inc',
        'SetAttribute'                  => __DIR__ . '/../include/simpleplugin/attributes/class_SetAttribute.inc',
        'HiddenAttribute'               => __DIR__ . '/../include/simpleplugin/attributes/class_HiddenAttribute.inc',
        'IntAttribute'                  => __DIR__ . '/../include/simpleplugin/attributes/class_IntAttribute.inc',
        'DateAttribute'                 => __DIR__ . '/../include/simpleplugin/attributes/class_DateAttribute.inc',
        'PhoneNumberAttribute'          => __DIR__ . '/../include/simpleplugin/attributes/class_PhoneNumberAttribute.inc',
        'MailsAttribute'                => __DIR__ . '/../include/simpleplugin/attributes/class_MailsAttribute.inc',
        'UserAttribute'                 => __DIR__ . '/../include/simpleplugin/attributes/class_ObjectSelectAttribute.inc',
        'DisplayAttribute'              => __DIR__ . '/../include/simpleplugin/attributes/class_DisplayAttribute.inc',
        'FileAttribute'                 => __DIR__ . '/../include/simpleplugin/attributes/class_FileAttribute.inc',
        'FakeAttribute'                 => __DIR__ . '/../include/simpleplugin/attributes/class_FakeAttribute.inc',
        'FlagsAttribute'                => __DIR__ . '/../include/simpleplugin/attributes/class_FlagsAttribute.inc',
        'ObjectSelectAttribute'         => __DIR__ . '/../include/simpleplugin/attributes/class_ObjectSelectAttribute.inc',
        'SimplePluginError'             => __DIR__ . '/../include/class_exceptions.inc',
        'FusionDirectoryException'      => __DIR__ . '/../include/class_exceptions.inc',
    ];

    if (isset($map[$relativeClass])) {
        $file = $map[$relativeClass];
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
