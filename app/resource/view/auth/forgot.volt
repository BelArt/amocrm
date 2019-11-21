{% extends "layout/narrow.volt" %}

{% block title %}Восстановление пароля{% endblock %}

{% block top %}
	{{ super() }}
	
	<div class="name">Восстановление пароля</div>
	<div class="back"><a title="Перейти  на главную" href="{{ url('/') }}">← вернуться</a></div>
	<div class="separate-line"></div>
{% endblock %}

{% block body %}
<form method="post">
	{{ form_row([form.get('name'),['class': 'margin-bottom-10']]) }}
	
	{{ form.render('submit') }}
</form>
{% endblock %}