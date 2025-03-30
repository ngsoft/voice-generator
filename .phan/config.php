<?php

$directories = [
    'src',
    'vendor',
    'public',
    'config',
];

$targetVersion = null;

if (is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.php-version')) {
    $targetVersion = trim(@file_get_contents(dirname(__DIR__) . '/.php-version'));
}

/*
 * This configuration will be read and overlaid on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 */
return [
    // Supported values: '7.0', '7.1', '7.2', '7.3', null.
    // If this is set to null,
    // then Phan assumes the PHP version which is closest to the minor version
    // of the php executable used to execute phan.
    'target_php_version' => $targetVersion,
    // A list of directories that should be parsed for class and
    // method information. After excluding the directories
    // defined in exclude_analysis_directory_list, the remaining
    // files will be statically analyzed for errors.
    //
    // Thus, both first-party and third-party code being used by
    // your application should be included in this list.
    'directory_list' => $directories,
    // A directory list that defines files that will be excluded
    // from static analysis, but whose class and method
    // information should be included.
    //
    // Generally, you'll want to include the directories for
    // third-party code (such as "vendor/") in this list.
    //
    // n.b.: If you'd like to parse but not analyze 3rd
    //       party code, directories containing that code
    //       should be added to the `directory_list` as
    //       to `exclude_analysis_directory_list`.
    'exclude_analysis_directory_list' => [
        'vendor/',
        'src/libs/',
    ],
    // A list of plugin files to execute.
    // See https://github.com/phan/phan/tree/master/.phan/plugins for even more.
    // (Pass these in as relative paths.
    // Base names without extensions such as 'AlwaysReturnPlugin'
    // can be used to refer to a plugin that is bundled with Phan)
    'plugins' => [
        // checks if a function, closure or method unconditionally returns.
        // can also be written as 'vendor/phan/phan/.phan/plugins/AlwaysReturnPlugin.php'
        'AlwaysReturnPlugin',
        // Checks for syntactically unreachable statements in
        // the global scope or function bodies.
        'UnreachableCodePlugin',
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
    ],
    'suppress_issue_types' => [
        'PhanRedefinedUsedTrait',
        'PhanRedefinedExtendedClass',
        'PhanRedefinedClassReference',
        'PhanUnreferencedClosure', // False positives seen with closures in arrays,
        'PhanPluginNoCommentOnProtectedMethod',
        'PhanPluginDescriptionlessCommentOnProtectedMethod',
        'PhanPluginNoCommentOnPrivateMethod',
        'PhanPluginDescriptionlessCommentOnPrivateMethod',
        'PhanPluginRedundantClosureComment',
        'PhanTypeInstantiateTraitStaticOrSelf',
        'PhanCompatibleObjectTypePHP71',
        'PhanUnusedVariableCaughtException',
        'PhanAccessReadOnlyMagicProperty',
        'PhanAccessMethodInternal',
        'PhanAccessMethodProtected',
        'PhanRedefinedInheritedInterface',
        'PhanUnusedPublicFinalMethodParameter',
        'PhanAccessOverridesFinalMethodInTrait',
        'PhanUnreferencedUseNormal',
        'PhanUnusedVariableValueOfForeachWithKey',
        'PhanAbstractStaticMethodCallInStatic',
        'PhanTypeMismatchArgumentNullableInternal',
        'PhanTypeInstantiateAbstractStatic',
        'PhanUndeclaredThis',
        'PhanUnusedPublicMethodParameter',
        'PhanPluginMixedKeyNoKey',
        'PhanTypeMismatchArgumentInternal',
        'PhanTypeMismatchDimAssignment',
        'PhanTypeMismatchArgumentNullable',
        'PhanParamTooFewUnpack',
        'PhanUnusedPublicNoOverrideMethodParameter',
        'PhanUnusedProtectedFinalMethodParameter',
        'PhanUnusedProtectedMethodParameter',
        'PhanTypeMismatchReturnSuperType',
        'PhanUnusedProtectedNoOverrideMethodParameter',
        'PhanTypeMismatchArgumentSuperType',
        'PhanPluginPrintfVariableFormatString',
        'PhanTypeInvalidThrowsIsInterface',
        'PhanRedefineClass',
        'PhanPluginPrintfIncompatibleArgumentType',
        'PhanTypeMismatchReturnNullable',
        'PhanTypeArraySuspiciousNullable',
        'PhanTemplateTypeNotUsedInFunctionReturn',
        'PhanInvalidFQSENInCallable'
    ],
    // If true, seemingly undeclared variables in the global
    // scope will be ignored.
    //
    // This is useful for projects with complicated cross-file
    // globals that you have no hope of fixing.
    'ignore_undeclared_variables_in_global_scope' => true,
    // If enabled, check all methods that override a
    // parent method to make sure its signature is
    // compatible with the parent's. This check
    // can add quite a bit of time to the analysis.
    // This will also check if final methods are overridden, etc.
    'analyze_signature_compatibility' => true,
];
