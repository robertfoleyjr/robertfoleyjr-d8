<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$databases['default']['default'] = array(
      'driver' => 'mysql',
      'database' => 'd8.robertfoleyjr.dev',
      'username' => 'drupal8user',
      'password' => 'password',
      'host' => 'localhost',
      'prefix' => '',
    );

$settings['trusted_host_patterns'] = array_merge($settings['trusted_host_patterns'], array(
    '^robertfoleyjr\.dev$',
    '^.+\.robertfoleyjr\.dev$',
  ));


