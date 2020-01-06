<?php

/* How to make new entries here for non-packaged languages:

-- 'code' should match the name of the directory for the language under \lang
.-- 'name' is the name that will be displayed in the language selection list (in Users and Display Setup)
-- 'rtl' only needs to be set for right-to-left languages like Arabic and Hebrew
-- 'encoding' used in translation file
-- 'version' always set to '' for manually installed languages.
-- 'path' installation path related to FA root (e.g. 'lang/en_US').
*/


$installed_languages = array (
  0 => 
  array (
    'code' => 'C',
    'name' => 'English',
    'encoding' => 'iso-8859-1',
  ),
  1 => 
  array (
    'name' => 'Arabic',
    'package' => 'ar_EG',
    'code' => 'ar_EG',
    'encoding' => 'utf-8',
    'version' => '2.4.1-3',
    'path' => 'lang/ar_EG',
    'rtl' => true,
  ),
  2 => 
  array (
    'name' => 'Bulgarian',
    'package' => 'bg_BG',
    'code' => 'bg_BG',
    'encoding' => 'utf-8',
    'version' => '2.4.1-3',
    'path' => 'lang/bg_BG',
  ),
  3 => 
  array (
    'name' => 'Bosnian',
    'package' => 'bs_BA',
    'code' => 'bs_BA',
    'encoding' => 'iso-8859-2',
    'version' => '2.4.1-3',
    'path' => 'lang/bs_BA',
  ),
  4 => 
  array (
    'name' => 'Danish',
    'package' => 'da_DK',
    'code' => 'da_DK',
    'encoding' => 'iso-8859-1',
    'version' => '2.4.1-3',
    'path' => 'lang/da_DK',
  ),
  5 => 
  array (
    'name' => 'Swiss German',
    'package' => 'de_CH',
    'code' => 'de_CH',
    'encoding' => 'iso-8859-1',
    'version' => '2.4.1-3',
    'path' => 'lang/de_CH',
  ),
  6 => 
  array (
    'name' => 'German',
    'package' => 'de_DE',
    'code' => 'de_DE',
    'encoding' => 'iso-8859-1',
    'version' => '2.4.1-3',
    'path' => 'lang/de_DE',
  ),
  7 => 
  array (
    'name' => 'Greek',
    'package' => 'el_GR',
    'code' => 'el_GR',
    'encoding' => 'UTF-8',
    'version' => '2.4.1-3',
    'path' => 'lang/el_GR',
  ),
  8 => 
  array (
    'name' => 'Australian English',
    'package' => 'en_AU',
    'code' => 'en_AU',
    'encoding' => 'iso-8859-1',
    'version' => '2.4.1-3',
    'path' => 'lang/en_AU',
  ),
  9 => 
  array (
    'name' => 'English (IN)',
    'package' => 'en_IN',
    'code' => 'en_IN',
    'encoding' => 'iso-8859-1',
    'version' => '2.4.1-3',
    'path' => 'lang/en_IN',
  ),
  10 => 
  array (
    'name' => 'English (NZ)',
    'package' => 'en_NZ',
    'code' => 'en_NZ',
    'encoding' => 'iso-8859-1',
    'version' => '2.4.1-3',
    'path' => 'lang/en_NZ',
  ),
  11 => 
  array (
    'name' => 'English (US)',
    'package' => 'en_US',
    'code' => 'en_US',
    'encoding' => 'iso-8859-1',
    'version' => '2.4.1-3',
    'path' => 'lang/en_US',
  ),
);

$dflt_lang = 'C';
