$(function() {
	$('.room-slider').slider({
		range: "min",
		min: 0,
		max: 100,
		value: 50,
		create: function(event, ui) {
			$(this).slider("option", "value", $(this).attr('data-value'));
		},
		stop: function(event, ui) {
			var room = $(this).parent().parent();
			$.get("api.php?fx=dim&type=room&uid=" + $(this).attr('data-room-id') + "&val=" + ui.value, function( data ) {
				console.log( data );
				$(room).find('.device-slider').each(function() {
					$(this).slider('value', ui.value );
				});
			});
		}
	});

	$('.device-slider').slider({
		range: "min",
		min: 0,
		max: 100,
		create: function(event, ui) {
			$(this).slider("option", "value", $(this).attr('data-value'));
			if ($(this).parent().hasClass('light-on')) {
				$(this).parent().css('background-color', 'rgba(255,255,0,' + ( $(this).attr('data-value') / 100 ) + ')');
			}
		},
		stop: function(event, ui) {
			$.get("api.php?fx=dim&type=device&uid=" + $(this).attr('data-device-id') + "&val=" + ui.value, function(data) {
			  console.log( data );


			});
		},
		slide: function( event, ui ) {
			if ($(this).parent().hasClass('light-on')) {
				$(this).parent().css('background-color', 'rgba(255,255,0,' + ( ui.value / 100 ) + ')');
			}
		}
	});

	$('.house-slider').slider({
		range: "min",
		min: 0,
		max: 100,
		value: 50,
		stop: function(event, ui) {
			$.get("api.php?fx=dim&type=all&uid=" + $(this).attr('data-device-id') + "&val=" + ui.value, function(data) {});
		}
	});

	$('button.onOffToggleButton').click(function(event) {
		var roomID = $(this).attr('data-room-id');
		var room = $(this).parent().parent();
		var val = 0;

		if ($(this).hasClass('buttonOn')) {
			val = 1;
		}

		$.get("api.php?fx=toggle&type=room&uid=" + roomID + "&val=" + val, function( data ) {
			  $(room).find('.room-devices .device').each(function() {
				if (1 === val) {
					$(this).addClass('light-on');
					var v = $(this).find('.device-slider').slider( "value" );
					$(this).css('background-color', 'rgba(255,255,0,' + ( v / 100 ) + ')');
				} else {
					$(this).removeClass('light-on');
					$(this).css('background-color','transparent');
				}
			});
		});
	});

	$('button.onOffDeviceToggleButton').click(function(event) {
		var val = 0;
		var light = $(this);
		if ('on' === $(this).data('toggle')) {
			val = 1;
		}

		$.get("api.php?fx=toggle&type=device&uid=" + $(this).attr('data-device-id') + "&val=" + val, function(data) {
			 if (1 === val) {
				$(light).parents('.device').addClass('light-on');
				var v = $(light).parents().find('.device-slider').slider("value");
				$(light).parents('.device').css('background-color', 'rgba(255,255,0,' + ( v / 100 ) + ')');
			} else {
				$(light).parents('.device').removeClass('light-on');
				$(light).parents('.device').css('background-color','transparent');
			}
		});
	});

	$('button.onOffHouseToggleButton').click(function(event) {
		var val = 0;

		if ($(this).hasClass('buttonOn')) {
			val = 1;
		}

		$.get("api.php?fx=toggle&type=all&uid=ALL&val=" + val, function(data) {
			$('.room-devices .device').each(function() {
				if (1 === val) {
					$(this).addClass('light-on');
					var v = $(this).find('.device-slider').slider( "value" );
					$(this).css('background-color', 'rgba(255,255,0,' + ( v / 100 ) + ')');
				} else {
					$(this).removeClass('light-on');
					$(this).css('background-color','transparent');
				}
			});

		});
	});

	$('.runScene').click(function(event) {
		var sid = $(this).attr('data-scene-id');
		var mode = $(this).attr('data-scene-mode');

		$.get("api.php?fx=scene&type=" + mode + "&uid=" + sid, function(data) {});
	});

	$('#arrayDump').click(function() {
		$(this).toggleClass('toggled');
	});
});