<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
	
	<!-- Makes your prototype chrome-less once bookmarked to your phone's home screen -->
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black">
	{% block meta %}{% endblock %}
	
    {# <link rel="shortcut icon" href="/favicon.ico"> #}

    <title>{% block title %}{% endblock %} | mmcoexpo</title>
	
	{{ stylesheet_link('/css/bootstrap.css?'~config.version) }}
    {% block stylesheets %}{% endblock %}

	<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body {% block body_attr %}{% endblock %}>

	{% block menu %}{% endblock %}
	{% block top %}{% endblock %}
	{% block flash %}
		{{ content() }}
	{% endblock %}
	{% block body %}{% endblock %}
	{% block bottom %}{% endblock %}
	
	{{ javascript_include('/js/bootstrap.js?'~config.version) }}
    {% block javascripts %}{% endblock %}
    
</body>
</html>