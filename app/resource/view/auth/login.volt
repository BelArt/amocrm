{% extends "layout/narrow.volt" %}

{% block title %}Авторизация{% endblock %}

{% block top %}
	{{ super() }}
	
	<div class="name">Авторизация</div>
{% endblock %}

{% block body %}
<form class="form-signin slip" method="post">
	{{ form_row([form.get('name')]) }}
	{{ form_row([form.get('pass')]) }}
	
	<div class="checkbox">
		<label>
			{{ form.render('rem') }} {{ form.get('rem').getLabel() }}
		</label>
		<div class="pull-right">
			<a href="{{ url('/auth/forgot') }}">Забыли пароль?</a>
		</div>
	</div>
	
	{{ form.render('submit') }}
</form>
{% endblock %}