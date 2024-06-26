#!/usr/bin/python
from selenium import webdriver
from selenium.common.exceptions import ElementNotInteractableException
import time
import sys
import os
import os.path
import traceback
import getopt

TMP_DIR = '/tmp/un'
SITE_LOGIN = ''
SITE_PASSWD = ''
ACCOUNT = ''

#############################################
# Handle CLI arguments
#############################################
def usage():
    print("Usage: myunfi.py --user=USER --password=PASSWORD --account=ACCOUNT\n")
    print("\t--user, -u\tUsername for MyUNFI")
    print("\t--password, -p\tPassword for MyUNFI")
    print("\t--account, -a\tAccount number")

try:
    opts, args = getopt.getopt(sys.argv[1:], "u:p:a:", ["user=", "password=", "account="])
except getopt.GetoptError as err:
    print((str(err)))
    usage()
    sys.exit(1)
for o, a in opts:
    if o in ("-u", "--user"): SITE_LOGIN=a
    elif o in ("-p", "--pass"): SITE_PASSWD=a
    elif o in ("-a", "--account"): ACCOUNT=a

if SITE_LOGIN == "" or SITE_PASSWD == "" or ACCOUNT == "":
    usage()
    sys.exit(1)

download_js = """
fetch('https://www.myunfi.com/shopping/export/v2/customers/%s/invoices/%s?transactionType=INVOICE&hostSystem=UBS', {
    headers: {
        'accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    }
})
.then(response => response.blob())
.then(blob => {
    var url = window.URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = "%s.xlsx";
    document.body.appendChild(a);
    a.click();    
    a.remove();        
});
"""

#############################################
# Initialize webdriver in headless mode
#
# Downloading files requires some extra
# setup for the driver to know where it
# should be saved
#############################################
def init_driver():
    chrome_opts = webdriver.ChromeOptions()
    chrome_opts.add_argument("--headless")
    chrome_opts.add_argument("window-size=1400,600")
    chrome_opts.add_experimental_option("prefs", { "download.default_directory": TMP_DIR })
    driver = webdriver.Chrome(chrome_options=chrome_opts)

    driver.command_executor._commands["send_command"] = ("POST", '/session/$sessionId/chromium/send_command')
    params = {'cmd': 'Page.setDownloadBehavior', 'params': {'behavior': 'allow', 'downloadPath': TMP_DIR}}
    command_result = driver.execute("send_command", params)

    if not(os.path.exists(TMP_DIR)):
        os.mkdir(TMP_DIR)

    return driver

os.chdir(os.path.dirname(os.path.abspath(__file__)))
exit_code = 0

try:
    driver = init_driver()
    driver.maximize_window()
    driver.get("https://myunfi.com")
    driver.find_element_by_name("USER").send_keys(SITE_LOGIN)
    driver.find_element_by_name("password").send_keys(SITE_PASSWD)
    driver.find_element_by_tag_name('button').click();
    time.sleep(2);
    #driver.get_screenshot_as_file("login.png")

    driver.get("https://www.myunfi.com/shopping")
    time.sleep(2);
    #driver.get_screenshot_as_file("shopping.png")

    driver.get("https://www.myunfi.com/shopping/orders/invoices?size=48")
    time.sleep(15)
    #driver.get_screenshot_as_file("invoices.png")

    invoices = []
    for elem in driver.find_elements_by_css_selector("td.MuiTableCell-alignLeft:nth-of-type(1) a"):
        invoices.append(elem.text)
    for inv_num in invoices:
        driver.get("https://www.myunfi.com/shopping/orders/invoices/" + inv_num + "/INVOICE")
        time.sleep(5)
        invoice_js = download_js % (ACCOUNT, inv_num, inv_num)
        driver.execute_script(invoice_js);
        time.sleep(3)
except Exception as e:
    print(e)
    traceback.print_exc()
    exit_code = 1
    print "Getting final screenshot"
    driver.get_screenshot_as_file("error.png")

driver.quit()
sys.exit(exit_code)

