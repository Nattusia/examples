
var selector1 = jQuery('#datetimepicker');
var selector2 = jQuery('#datetimepicker2');
var clientTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
// (function ($, Drupal) {
//   Drupal.behaviors.scheduling_behaviour = {
//     attach: function (context, settings) {
jQuery(document).ready(function() {
  //console.log(drupalSettings);

    dateTimePickerInit(selector1, drupalSettings.datetimepicker);
    dateTimePickerInit(selector2, drupalSettings.datetimepicker);
    dateTimePickerHide(selector2);
    var startDate = selector1.datetimepicker('getValue');
    setInitDate(selector1, startDate, drupalSettings.datetimepicker);

    jQuery('input[name = "timezone"]').val(clientTimezone);

    jQuery('#edit-recursive').click(function() {
      jQuery('#edit-pattern').trigger('click');
      jQuery('#edit-single').toggleClass('hidden');
      jQuery(this).toggleClass('hidden');
      jQuery('#selected-time').addClass('period');
      return false;
    });

    jQuery('#edit-single').click(function() {
      jQuery('#edit-pattern').trigger('click');
      jQuery('#edit-recursive').toggleClass('hidden');
      jQuery('#selected-time').removeClass('period');
      jQuery(this).toggleClass('hidden');
      return false;
    });

    jQuery('#edit-pattern').click(function() {
      if (jQuery(this).prop('checked')) {
        var labelMarkup1 = '<div class = "temp-markup"><strong>Start Date and Time</strong></div>';
        var labelMarkup2 = '<div class = "temp-markup"><strong>End Date</strong></div>';
        dateTimePickerShow(selector2);
        dateToSend = new Date(selector1.datetimepicker('getValue'));
        getTomorrow(dateToSend, drupalSettings.datetimepicker);
        jQuery('.temp-markup').remove();
        selector1.parent('.dt-wrapper').prepend(labelMarkup1);
        selector2.parent('.dt-wrapper').prepend(labelMarkup2);
        var startDate = selector1.datetimepicker('getValue');
        setInitDate(selector1, startDate, drupalSettings.datetimepicker);
      }
      else {
        dateTimePickerHide(selector2);
        jQuery('.temp-markup').remove();
        var startDate = selector1.datetimepicker('getValue');
        setInitDate(selector1, startDate, drupalSettings.datetimepicker);
      }
    });

    var checkbox = jQuery('input[id ^= "edit-days"]');
    var weekDays = drupalSettings.datetimepicker.week_days;
    checkbox.each(function() {
      var checkboxValue = jQuery(this).attr('value');
      if (jQuery.inArray(checkboxValue, weekDays) == -1) {
        jQuery(this).attr('disabled', 'disabled');
      }
    });
});


jQuery(document).ajaxComplete(function(event, xhr, settings) {
  console.log(event);
  console.log(xhr);
  console.log(settings);

  var sets = xhr.responseJSON;
  if (sets.selectorId != undefined) {
    drupalSettings.datetimepicker = sets;

    var selector = jQuery('#' + sets.selectorId);
    dateTimePickerDestroy(selector);
    dateTimePickerInit(selector, sets);
    var dp = new Date(sets.defaultDate + ' ' + sets.defaultTime);
    setInitDate(selector, dp, sets);

    if (jQuery('#edit-pattern').prop('checked')) {
      dateTimePickerShow(selector2);
    }
    else {
      dateTimePickerHide(selector2);
    }
  }
});

function dateTimePickerHide(selector) {
  selector.next('.xdsoft_datetimepicker').hide();
}

function dateTimePickerShow(selector) {
  //selector.datetimepicker('show');
  selector.next('.xdsoft_datetimepicker').show();
}

function dateTimePickerDestroy(selector) {
  selector.datetimepicker('destroy');
}
function dateTimePickerInit(selector, settings) {
    selector.datetimepicker({
    format:'Y-m-d\Tg:iA',
    timeFormat: 'g:iA',
    inline:true,
    lang:'ru',
    hours12: true,
    timepicker: selector.attr('id') == 'datetimepicker',
    onChangeDateTime:function(dp,$input){
      setInitDate(selector, dp, settings);

    },
    onChangeMonth:function(dp, $input) {

      var timeSet = jQuery('input[name = "time"]').val();
      var dateOrigin = new Date(timeSet);

      var month = parseInt(dp.getMonth()) + 1;
      var selectorId = selector.attr('id');
      jQuery.ajax({
        type: "POST",
        url: "/month/" + settings.coach + '/' + month + '/' + settings.template,
        data: { 'selectorId' : selectorId, 'monthOrigin' : dateOrigin.getMonth(), 'year' : dp.getFullYear() },
        dataType: "JSON",
      }).done(function(data) {
        //console.log(data);
      });


    },

    onSelectDate:function(ct,$i){
      var d = jQuery('#datetimepicker').datetimepicker('getValue');
      var number = d.getDate();  //console.log(number);
      var allowTimes = [];
      var defaultTime = settings.defaultTime;

      if (settings.allowTimes[number] != undefined) {
        allowTimes = settings.allowTimes[number];
        defaultTime = settings.allowTimes[number][0];
      }
        this.setOptions({
          allowTimes: allowTimes,
          defaultTime: defaultTime,
        });

    },
    allowTimes: settings.defaultHours,
    disabledDates: settings.disabledDates,//['05.08.2021','06.08.2021','10.08.2021','11.08.2021','15.08.2021','16.08.2021'],
    defaultDate: settings.defaultDate,
    defaultTime: settings.defaultTime,
    minDate: settings.minDate,
    formatDate:'Y-m-d',
    scrollTime: false,
    scrollInput: false,
  });
}

function setInitDate(selector, dp, settings) {

  var month = parseInt(dp.getMonth()) + 1;
  var dateArr = [dp.getFullYear(), month, dp.getDate()];
  var hours = dp.getHours();
  var minutes = dp.getMinutes();
  var dateStr = dateArr.join('-');
  var inputName = selector.attr('id') == 'datetimepicker' ? 'time' : 'end';
  jQuery('input[name="'+ inputName + '"]').val(dateStr + ' ' + hours + ':' + minutes);

  var dateToSend = new Date(dp);

  if (selector.attr('id') == 'datetimepicker') {

    var futureDateValue = selector2.datetimepicker('getValue');
    if (futureDateValue < dateToSend) {
      getTomorrow(dateToSend, settings);
    }
  }
  else {
    var pastDateValue = selector1.datetimepicker('getValue');
    if (pastDateValue > dateToSend) {
      getYesterday(dateToSend, settings);
    }
  }

  outputDate();
}

function outputDate() {
  dp = selector1.datetimepicker('getValue');

  var month = parseInt(dp.getMonth()) + 1;
  var dateArr = [dp.getFullYear(), month, dp.getDate()];
  //var hours = dp.getHours();
  //var minutes = dp.getMinutes();
  var hoursMinutes = dp.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
  var dateStr = dateArr.join('-');
  var weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];


  var displayNumber = parseInt(dp.getDate()) < 10 ? '0' + dp.getDate() : dp.getDate();
  var displayDate = [weekDays[dp.getDay()], displayNumber, months[dp.getMonth()], dp.getFullYear()];


  var timestamp = Date.parse(dp);
  var endTime = timestamp + drupalSettings.datetimepicker.interval * 1000;

  var endDate = new Date(endTime);
  var endHoursMinutes = endDate.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
  //var endHours = endDate.getHours();
  //var endMinutes = parseInt(endDate.getMinutes()) < 10 ? '0' + endDate.getMinutes() : endDate.getMinutes();
  //var displayMinutes = parseInt(minutes) < 10 ? '0' + minutes : minutes;

  //var meetingType = drupalSettings.datetimepicker.template.charAt(0).toUpperCase() + drupalSettings.datetimepicker.template.slice(1);
  var meetingType = drupalSettings.datetimepicker.template;

  jQuery('#selected-time .start-date-desc').text(meetingType + ' date');
  //var newTimeFrom = '<span>from </span><span>' + hours + ' : ' + displayMinutes + '</span>';
  //var newTimeTo = '<span> to </span><span>' + endHours + ' : ' + endMinutes + '</span>';
  var newTimeFrom = '<span>from </span><span>' + hoursMinutes + '</span>';
  var newTimeTo = '<span> to </span><span>' + endHoursMinutes + '</span>';

  jQuery('#selected-time .start-date-date').text(displayDate.join(' '));
  jQuery('#selected-time .start-time').html(newTimeFrom + newTimeTo);


  if (jQuery('#edit-pattern').prop('checked')) {
    jQuery('#selected-time .start-date-desc').text('');
    var startDesc = jQuery('#selected-time .start-date-date').text();
    var finval = jQuery('input[name = "end"]').val();

    var fin = selector2.datetimepicker('getValue');
    var displayFinNumber = parseInt(fin.getDate()) < 10 ? '0' + fin.getDate() : fin.getDate();
    var endFinishing = [weekDays[fin.getDay()], displayFinNumber, months[fin.getMonth()], fin.getFullYear()];

    jQuery('#selected-time .start-date-date').
      text('Start date: ' + startDesc + ' End date: ' + endFinishing.join(' '));
  }
}

function getTomorrow(date, settings) {
  date.setDate(date.getDate() + 1);
  var month = parseInt(date.getMonth()) + 1;
  month = month < 10 ? '0' + month : month;
  monthDay = parseInt(date.getDate()) < 10 ? '0' + date.getDate() : date.getDate();
  var dateArr = [date.getFullYear(), month, monthDay];
  var dateStr = dateArr.join('-');
  if (jQuery.inArray(dateStr, settings.disabledDates) == -1) {
    jQuery('input[name="end"]').val(dateArr.join('-'));
    settings.defaultDate = dateStr;

    dateTimePickerDestroy(selector2);
    dateTimePickerInit(selector2, settings);
    //clickCalendar(date);
    if (!jQuery('#edit-pattern').prop('checked')) {
      dateTimePickerHide(selector2);
    }
  }
  else {
    getTomorrow(date, settings);
  }
}

function getYesterday(date, settings) {
  date.setDate(date.getDate() - 1);
  var month = parseInt(date.getMonth()) + 1;
  month = month < 10 ? '0' + month : month;
  monthDay = parseInt(date.getDate()) < 10 ? '0' + date.getDate() : date.getDate();
  var dateArr = [date.getFullYear(), month, monthDay];
  var dateStr = dateArr.join('-');
  if (jQuery.inArray(dateStr, settings.disabledDates) == -1) {
    jQuery('input[name="end"]').val(dateArr.join('-'));
    settings.defaultDate = dateStr;
    dateTimePickerDestroy(selector1);
    dateTimePickerInit(selector1, settings);
    //clickCalendar(date);
  }
  else {
    getYesterday(date, settings);
  }
}

function clickCalendar(date) {
  var calendar = selector2.next('.xdsoft_datetimepicker').find('.xdsoft_calendar');

  if (calendar.html() != '') {
    calendar.find('table td[data-month = "' + date.getMonth() + '"][data-date = "' + date.getDate() + '"]').trigger('click');
  }
}

//     }
//   };
// })(jQuery, Drupal);

