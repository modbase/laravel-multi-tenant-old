<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title>Laravel: A Framework For Web Artisans</title>
	<meta name="viewport" content="width=device-width">
	{{ HTML::style('laravel/css/style.css') }}
</head>
<body>
	<div class="wrapper">
		<header>
			<h1>Laravel (Multi Tenant)</h1>
			<h2>A Multi Tenant Framework For Web Artisans</h2>

			<p class="intro-text" style="margin-top: 45px;">
			</p>
		</header>
		<div role="main" class="main">
			<div class="home">
				<h2>Verification</h2>

				<p>Great! You're now using this database:</p>
				
				<pre><?= Config::get('database.connections.mysql.database') ?></pre>

				<h2>Grow in knowledge.</h2>

				<p>
					Learning to use Laravel is amazingly simple thanks to
					its {{ HTML::link('docs', 'wonderful documentation') }}.
				</p>

				<h2>Create something beautiful.</h2>

				<p>
					Now that you're up and running, it's time to start creating!
					Here are some links to help you get started:
				</p>

				<ul class="out-links">
					<li><a href="https://github.com/modbase/laravel-multi-tenant">Multi tenancy GitHub Repository</a></li>
					<li><a href="http://laravel.com">Laravel Official Website</a></li>
					<li><a href="http://forums.laravel.com">Laravel Forums</a></li>
					<li><a href="http://github.com/laravel/laravel">Laravel GitHub Repository</a></li>
				</ul>
			</div>
		</div>
	</div>
</body>
</html>
