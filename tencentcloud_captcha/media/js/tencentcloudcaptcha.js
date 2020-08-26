function tencentcaptcha(){

    document.getElementById('codeVerifyTicket').value='';
    document.getElementById('codeVerifyTicket').value='';
    var captcha1 = new TencentCaptcha( document.getElementById('codeVerifyButton').getAttribute('data-appid'), function (res) {
        if (res.ret == 0) {
            document.getElementById('codeVerifyTicket').value=res.ticket;
            document.getElementById('codeVerifyRandstr').value=res.randstr;
            document.getElementById('codeVerifyButton').style.display="none";
            document.getElementById('codePassButton').style.display="block";
        }
    });
    captcha1.show();
};