{% extends "layout/narrow.volt" %}

{% block title %}Изменение пароля{% endblock %}

{% block top %}
	{{ super() }}
	
	<div class="name">Изменение пароля</div>
	<div class="back"><a title="Перейти на главную" href="{{ url('/') }}">← вернуться</a></div>
	<div class="separate-line"></div>
{% endblock %}

{% block body %}
<form method="post">
	{{ form_row([form.get('pass')]) }}
	{{ form_row([form.get('confirmPass')]) }}
	
	{{ form.render('submit') }}
	{{ form.render('csrf') }}
</form>
{% endblock %}