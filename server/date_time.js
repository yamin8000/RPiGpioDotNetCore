/**
 * @author: Yamin Siahmargooei
 * Original Author: http://www.webestools.com/scripts_tutorials-code-source-7-display-date-and-time-in-javascript-real-time-clock-javascript-date-time.html
 * @copyright Golestan University, School of Engineering
 * @year 2018
 * Supervisor: Mohammad Maghsoudloo
 * B.S. Project
 * IoT Cloud Research Center
 */

function date_time(id) {
    var date = new Date();
    var year = date.getFullYear();
    var month = date.getMonth();
    var months = ['January', 'February', 'March', 'April', 'May', 'June', 'Jully', 'August', 'September', 'October', 'November', 'December'];
    var d = date.getDate();
    var day = date.getDay();
    var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    var h = date.getHours();
    if (h < 10) {
        h = "0" + h;
    }
    var m = date.getMinutes();
    if (m < 10) {
        m = "0" + m;
    }
    var s = date.getSeconds();
    if (s < 10) {
        s = "0" + s;
    }
    document.getElementById(id).innerHTML = '' + days[day] + ' ' + months[month] + ' ' + d + ' ' + year + ' ' + h + ':' + m + ':' + s;
    setTimeout('date_time("' + id + '");', '1000');
    return true;
}