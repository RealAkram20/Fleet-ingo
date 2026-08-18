{{--
    Bot trap. Hidden from people in three ways so that a screen reader does not
    announce it and a browser does not autofill it, while a scripted client that
    simply fills every input still walks into it.
--}}
<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
    <label for="{{ \App\Http\Middleware\Honeypot::FIELD }}">Leave this field empty</label>
    <input type="text"
           id="{{ \App\Http\Middleware\Honeypot::FIELD }}"
           name="{{ \App\Http\Middleware\Honeypot::FIELD }}"
           value=""
           tabindex="-1"
           autocomplete="off">
</div>

<input type="hidden"
       name="{{ \App\Http\Middleware\Honeypot::TIMER }}"
       value="{{ time() }}">
