<!DOCTYPE html>
<html dir="ltr" lang="en-US">
<head>
<meta charset="UTF-8" />

<?php App::do_action('the_header_meta'); ?>

<link id="favicon" rel="shortcut icon" href="/img/favicon.ico" />
<link rel="profile" href="http://gmpg.org/xfn/11" />

<title><?php echo $title; ?></title>

<?php App::do_action('the_header'); ?>

</head>
<body>

<div id="page" class="container">

	<div class="navbar navbar-fixed-top">
        <div class="navbar-inner">
            <div class="container">
                <a class="brand" href="<?php App::the_url(); ?>">
                    oBuilder
                </a>
                            
                <ul class="nav pull-right">
				<?php if (Auth::is('admin')) : ?>
                	<li><a href="<?php App::the_url_enc('users', 'profile') ?>">Welcome, <?php echo Auth::name(); ?>!</a></li>
                    <li><a href="<?php App::the_url_enc('users', 'logout') ?>">Logout</a></li>
                <?php else : ?>
                	<li><a href="<?php App::the_url_enc('users', 'login') ?>">Login</a></li>
                
                	<?php if (Conf::spec('allow_register')) : ?>
                	<li><a href="<?php App::the_url_enc('users', 'register') ?>">Register</a></li>
                    <?php endif; ?>
                <?php endif; ?>
                </ul>
                
            </div>
        </div>
    </div>  

    <div class="page-header">
        <h1><?php echo !empty($title) ? $title : ucfirst(App::$ctrl); ?></h1>
    </div>
