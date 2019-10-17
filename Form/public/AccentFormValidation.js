/**
 * Part of the Accent framework.
 *
 * @author     Miroslav Ćurčić <office@tekod.com>
 * @license    MIT License
 * @link       http://www.accentphp.com
 */


if (typeof (Accent) === "undefined") {
  Accent= {};
}

/* Class FormValidation in Accent namespace */
Accent.FormValidation= {

    // permition to setup onsubmit event handler
    SetupOnSubmit: true,

    // permition to set focus on control with error
    FocusedOnFirstError: false,

    // translations of error messages
    Messages: {
       /* 'Email':'ovo polje nije ispravna email adresa',
        * 'EmailNamed':'ovo polje nije ispravna email adresa',
        * 'InRange':'ovo polje nije u opsegu dozvoljenih vrednosti',
        * 'Max':'ovo polje je iznad maksimalne dozvoljene vrednosti',
        * 'Min':'ovo polje je ispod minimalne dozvoljene vrednosti',
        * 'In':'ovo polje ne sadrži nijednu od dozvoljenih vrednosti',
        * 'Len':'ovo polje ima neispravnu dužinu',
        * 'LenMax':'ovo polje ima dužinu iznad dozvoljene',
        * 'LenMin':'ovo polje ima dužinu ispod dozvoljene',
        * 'LenRange':'ovo polje ima dužinu van dozvoljenog opsega',
        * 'RegEx':'ovo polje nema ispravan format sadržaja',
        * 'FileName':'ovo polje može sadržavati samo: A-Z,0-9,~,_,!,|,.,-',
        * 'URL':'ovo polje sadrži znake zabranjene za URL adresu',
        * 'Date':'ovo polje je neispravan datum',
        * 'Required':'ovo polje je obavezno',
        * 'IPv4':'ovo polje nije ispravna IPv4 adresa',
        * 'IP':'ovo polje nije ispravna IP adresa',
        * 'Alpha':'ovo polje može sadržavati samo slova',
        * 'Alnum':'ovo polje mora biti alfa-numeričko',
        * 'Integer':'ovo polje nije ispravan prirodni broj',
        * 'Float':'ovo polje je neispravan ili prevelik decimalan broj',
        * 'CreditCard':'ovo polje je neispravan broj platne kartice',
        * 'Decimal':'ovo polje je neispravan ili prevelik decimalan broj',
        * 'Digits':'ovo polje može sadržavati samo brojeve',
        * 'SameInput':'ovo polje se ne poklapa'*/
    },

    // internal buffers
    CurrentForm: {},



    SetMessages: function(Messages) {
        Accent.FormValidation.Messages= Messages;
    },

    GetValue: function(Element) {
        var i;
        if (!Element) {
            return null;
        } else if (!Element.tagName) {
            return Element[0] ? Accent.FormValidation.GetValue(Element[0]) : null;
        } else if (Element.type === 'file') {
            return Element.files || Element.value;
        } else if (Element.type === 'radio') {
            var Elements= Element.form.elements;
            for (i= Elements.length-1; i >= 0; i--) {
                if (Elements[i].name === Element.name && Elements[i].checked) {
                    return Elements[i].value;
                }
            }
            return null;
        } else if (Element.tagName === 'select') {
            var Index= Element.selectedIndex;
            var Options= Element.options;
            if (Element.type === 'select-one') {
                return Index < 0 ? null : Options[Index].value;
            }
            var Values= [];
            for (i= 0; i < Options.length; i++) {
                if (Options[i].selected) {
                    Values.push(Options[i].value);
                }
            }
            return Values;
        } else if (Element.name && Element.name.match(/\[\]$/)) { // name with trailing []
            var Elements= Element.form.elements[Element.name].tagName
                ? [Element]
                : Element.form.elements[Element.name];
            var Values= [];
            for (i= 0; i < Elements.length; i++) {
                if (Elements[i].type !== 'checkbox' || Elements[i].checked) {
                    Values.push(Elements[i].value);
                }
            }
            return Values;
        } else if (Element.type === 'checkbox') {
            return Element.checked;
        } else if (Element.tagName === 'textarea') {
            return Element.value.replace("\r", '');
        } else {
            return Element.value.replace("\r", '').replace(/^\s+|\s+$/g, '');
        }
    },


    ValidateControl: function(Element, CheckOnly) {

        if (Accent.FormValidation.IsDisabled(Element)) {
            return true;
        }
        Accent.FormValidation.CurrentForm= Element.form;
        var Rules= Element.form.AccentRules;
        var Name= Element.getAttribute('name');
        if (!Rules || !Rules[Name]) {
            return true;
        }
        Rules= Rules[Name].split('|');
        var Value= Accent.FormValidation.GetValue(Element);
        for (var i= 0, Len= Rules.length; i < Len; i++) {
            var Rule= Rules[i].split(':');
            var Validator= Rule.shift();            //alert(Validator);
            if (!Accent.FormValidation.Validators[Validator]) {
                continue;
            }
            var Success= Accent.FormValidation.Validators[Validator](Value, Rule.join(':'));
            if (Success === false) {
                if ((Accent.FormValidation.Messages[Validator]) && !CheckOnly) {
                    var Message= Accent.FormValidation.Messages[Validator];
                    Accent.FormValidation.ShowError(Element, Message);
                }
                return false;
            }
            if (Success === null) {
                break; // skip rest of validations
            }
        }
        Accent.FormValidation.ShowError(Element, false);
        return true;
    },


    ValidateForm: function(Form) {

        Accent.FormValidation.CurrentForm= Form;
        var Radios= {}, i, Element;
        for (i= 0; i < Form.elements.length; i++) {
            Element= Form.elements[i];
            if (Element.tagName && !(Element.tagName.toLowerCase() in {input:1,select:1,textarea:1,button:1})) {
                alert('Skippping '+Element.tagName.toLowerCase());
                continue;
            } else if (Element.type === 'radio') {
                if (Radios[Element.name]) {
                    continue; // skip duplicated validation
                }
                Radios[Element.name]= true;
            }
            if (!Accent.FormValidation.ValidateControl(Element)) {
                return false;
            }
        }
        return true;
    },


    IsDisabled: function(Element) {

        if (Element.type === 'radio') {
            for (var i = 0, Elements = Element.form.elements; i < Elements.length; i++) {
                if (Elements[i].name === Element.name && !Elements[i].disabled) {
                    return false;
                }
            }
            return true;
        }
        return Element.disabled;
    },


    ShowError: function(Element, Message) {

        var Container= Accent.FormValidation.GetErrorContainer(Element);
        if (Message === false) {
            Container.setAttribute('class', 'fError fErrorTransition');
            setTimeout(function(){Container.innerHTML= '';}, 300);
        } else {
            Container.setAttribute('class', 'fError fErrorTransition');
            setTimeout(function(){Container.setAttribute('class', 'fError');}, 10);
            Container.innerHTML= '<ul><li>'+Message+'</li></ul>';
        }
        if (Accent.FormValidation.FocusedOnFirstError === false) {
            if (Element.focus) {
                Element.focus();
            }
            Accent.FormValidation.FocusedOnFirstError= true;
        }
    },


    GetErrorContainer: function(Element) {

        var ErrorContainer= Element.parentNode.getElementsByClassName('fError');
        if (ErrorContainer.length === 0) {
            ErrorContainer= document.createElement('div');
            ErrorContainer.setAttribute('class', 'fError');
            Element.parentNode.appendChild(ErrorContainer);
            return ErrorContainer;
        } else {
            return ErrorContainer[0];
        }
    },


    /* util method, check class */
    HasClass: function(Element, ClassName) {
        var Classes= Element.className;
        var Pattern= new RegExp(ClassName, 'g');
        return (Pattern.test(Classes)) ? true : false;
    },

    /* initialize specified form element */
    InitForm: function(Form) {
        // parse rules
        var Rules= Form.getAttribute('data-afv');
        if (!Rules) {
            return;
        }
        Rules= Rules.replace(/'/g, '"');
        Form.AccentRules= window.JSON && window.JSON.parse ? JSON.parse(Rules) : eval(Rules);
        // attach onsubmit handler
        if (Accent.FormValidation.SetupOnSubmit) {
            Accent.FormValidation.AddEvent(Form, 'submit', function(e) {
                Accent.FormValidation.FocusedOnFirstError= false;
                if (!Accent.FormValidation.ValidateForm(Form)) {
                    if (e && e.stopPropagation) {
                        e.stopPropagation();
                    } else if (window.event) {
                        event.cancelBubble= true;
                    }
                    e.preventDefault();
                    return false;
                }});  }
        // attach delegated onblur handler
        Accent.FormValidation.AddEvent(Form, 'blur', function(e) {
            var Target= e.target || e.srcElement;
            var Tag= Target.tagName.toLowerCase();
            if (!Tag || !(Tag in {input:1,select:1,textarea:1,button:1})) {
                return;
            }
            Accent.FormValidation.FocusedOnFirstError= false;
            Accent.FormValidation.ValidateControl(Target);
        });
    },

    LenUtf: function(Str) {
        var Len= 0;
        for(var i= 0; i < Str.length; i++) {
            var Code= Str.charCodeAt(i);
            if (Code <= 0x7f) {Len += 1;}
            else if (Code <= 0x7ff) {Len += 2;}
            else if (Code >= 0xd800 && Code <= 0xdfff) {Len += 4; i++;}
            else if (Code < 0xffff) {Len += 3;}
            else {Len += 4;}
        }
        return Len;
    },

    GetElementByName: function(Name) {
        var Elements= Accent.FormValidation.CurrentForm.elements;
        for (i= Elements.length-1; i >= 0; i--) {
            if (Elements[i].name === Name) {
                return Elements[i];
            }
        }
    },


    //====================================
    //               Events
    // ===================================


    /* handler for onload event */
    OnLoad: function() {
        var Forms= document.getElementsByTagName('form');
        for (var i= 0; i < Forms.length; i++) {
            Accent.FormValidation.InitForm(Forms[i]);
        }
    },


    /* event attacher */
    AddEvent: function(Element, Event, Handler) {
        if (Element.attachEvent) { // IE (6+?)
          Element.attachEvent('on' + Event, Handler);
        } else if (Element.addEventListener) { // Most nice browsers
          Element.addEventListener(Event, Handler, true);
        } else { // Old browsers
          if (!Element.id) {
            var d= new Date();
            Element.id= d.getTime(); // Assign an id based on the time if it has no id
          }
          eval('document.getElementById('+Element.id+').on'+Event+'='+Handler);
        }
    },

    /* onload event launcher */
    RunOnLoad: function(Func) {
        var OldOnLoad= window.onload;
        if (typeof window.onload !== 'function') {
            window.onload = Func;
        } else {
            window.onload = function() {
                if (OldOnLoad) {
                    OldOnLoad();
                }
                Func();
            };
        }
    },



    //====================================
    //           All validators
    // ===================================


    Validators: {

        Equal: function(Value, Options) {
            return Value === Options;
        },

        Email: function(Value, Options) {
            return (/^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/).test(Value);
        },

        InRange: function(Value, Options) {
            var Limits= Options.split("..");
            Value= parseFloat(Value);
            return parseFloat(Limits[0]) <= Value && Value <= parseFloat(Limits[1]);
        },

        Max: function(Value, Options) {
            return Value <= parseFloat(Options);
        },

        Min: function(Value, Options) {
            return Value >= parseFloat(Options);
        },

        In: function(Value, Options) {
            return Options.split(',').indexOf(Value) !== -1;
        },

        Len: function(Value, Options) {
            return Accent.FormValidation.LenUtf(Value) === parseInt(Options);
        },

        LenMax: function(Value, Options) {
            return Accent.FormValidation.LenUtf(Value) <= parseInt(Options);
        },

        LenMin: function(Value, Options) {
            return Accent.FormValidation.LenUtf(Value) >= parseInt(Options);
        },

        LenRange: function(Value, Options) {
            var Limits= Options.split("..");
            Value= Accent.FormValidation.LenUtf(Value);
            return parseInt(Limits[0]) <= Value && Value <= parseInt(Limits[1]);
        },

        RegEx: function(Value, Options) {// must use "/" delimiter
            var Match = Options.match(new RegExp('^/(.*?)/([gimy]*)$'));
            try {
                var RegEx = new RegExp(Match[1], Match[2]);
                return RegEx.test(Value);
            } catch (e) {}
        },

        FileName: function(Value, Options) {
            return Value.replace(/[^A-Za-z0-9~_.!\|-]/, '') === Value;
        },

        URL: function(Value, Options) {
            return (/^(?:(?:https?|ftp):\/\/)?(?:\S+(?::\S*)?@)?(?:(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)(?:\.(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)*(?:\.(?:[a-z\u00a1-\uffff]{2,})))(?::\d{2,5})?(?:\/\S*)?$/i).test(Value);
        },

        /*Date: function(Value, Options) {
            return (/^.....$/).test(Value);
        },*/

        Required: function(Value, Options) {
            return Value !== '' && Value !== false && Value !== null;
        },

        IP: function(Value, Options) {
            return ((/^(?:(?:2[0-4]\d|25[0-5]|1\d{2}|[1-9]?\d)\.){3}(?:2[0-4]\d|25[0-5]|1\d{2}|[1-9]?\d)$/).test(Value)
              || (/^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/).test(Value));
        },

        IPv4: function(Value, Options) {
            return (/^(?:(?:2[0-4]\d|25[0-5]|1\d{2}|[1-9]?\d)\.){3}(?:2[0-4]\d|25[0-5]|1\d{2}|[1-9]?\d)$/).test(Value);
        },

        Alpha: function(Value, Options) {
            return (/^[a-z\u00BF-\u1FFF\u2C00-\uD7FF]+$/i).test(Value);
        },

        Alnum: function(Value, Options) {
            return (/^[a-z0-9\u00BF-\u1FFF\u2C00-\uD7FF]+$/i).test(Value);
        },

        Integer: function(Value, Options) {
            return (/^\-?[0-9]+$/).test(Value);
        },

        Float: function(Value, Options) {
            var Sep= Options.split('/');
            Sep= [new RegExp('\\'+Sep[0], 'g'), new RegExp('\\'+Sep[1], 'g')];
            Value= Value.replace(Sep[0], '').replace(Sep[1],'.');  // de-localize number
            return (/^[+-]?[0-9]*\.?[0-9]+$/).test(Value);
        },

        CreditCard: function(Value, Options) {
            Value= Value.replace(/[ -]/g, ''); // remove "-" and " " chars
            return (/^(?:(4[0-9]{12}(?:[0-9]{3})?)|(5[1-5][0-9]{14})|(6(?:011|5[0-9]{2})[0-9]{12})|(3[47][0-9]{13})|(3(?:0[0-5]|[68][0-9])[0-9]{11})|((?:2131|1800|35[0-9]{3})[0-9]{11}))$/).test(Value);
        },

        Decimal: function(Value, Options) {
            var Options= Options.split('/');
            var Sep= [new RegExp('\\'+Options[0], 'g'), new RegExp('\\'+Options[1], 'g')];
            Value= Value.replace(Sep[0], '').replace(Sep[1],'.');  // de-localize number
            if (!(/^[+-]?[0-9]*\.?[0-9]+$/).test(Value)) return false; // invalid float
            var Lens= Options[2].split('.');
            var Parts= Value.split('.');
            Parts[0]= Parts[0] ? parseInt(Parts[0]).toString().length : 0;
            Parts[1]= Parts[1] ? parseInt(Parts[1]).toString().length : 0;
            return Parts[0]+Parts[1] <= parseInt(Lens[0]) && Parts[1] <= parseInt(Lens[1]);
        },

        Digits: function(Value, Options) {
            return (/^[0-9]+$/).test(Value);
        },

        SkipIf: function(Value, Options) {
            Options= Options.split(':');
            var Targ= Accent.FormValidation.GetElementByName(Options[0]);
            if (!Targ) return false;
            if (!Accent.FormValidation.Validators[Options[1]]) return true;
            var RefValue= Accent.FormValidation.GetValue(Targ);
            var Success= Accent.FormValidation.Validators[Options[1]](RefValue, Options[2]);
            return Success === true ? null : true; // skip rest of validations or Ok
        },

        SameInput: function(Value, Options) {
            var Targ= Accent.FormValidation.GetElementByName(Options);
            if (!Targ) return false;
            var RefValue= Accent.FormValidation.GetValue(Targ);
            return Value === RefValue;
        }

        /*
        InIpRange: function(Value, Options) {
            return (/^...$/).test(Value);
        }*/

    }


};

Accent.FormValidation.RunOnLoad(Accent.FormValidation.OnLoad);


/*
 * <script src="./AcentformValidation.js"></script>
 * optionaly: <script>Accent.FormValidation.SetMessages({...});</script>
 */