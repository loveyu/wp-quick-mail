#!/usr/local/bin/python3
# author: loveyu admin@loveyu.info
# run: nohup quick_mail.py > qucik_mail.log 2>&1 &
import socket
import threading
import datetime
import subprocess

PHP_SCRIPT = "/wordpress/quick_mail.php" # 你的qucik_mail.php 文件路径
S_IP = "127.0.0.1" # 当前服务监听的IP地址
S_PORT = 2789 # 当前监听的端口

BUF_SIZE = 1024

def write_log(msg):
    time = datetime.datetime.now()  # 获得当前时间
    time = time.strftime('%Y-%m-%d %H:%M:%S')
    print("[%s] %s" % (time, msg))


class RunPHP(threading.Thread): # 运行线程
    def __init__(self, client, address):
        threading.Thread.__init__(self)
        self.client = client
        self.address = address

    def send_mail(self, qm_id):
        write_log("%s:%s run script: %s" % (self.address[0], self.address[1], qm_id))
        p = subprocess.Popen("php %s %s" % (PHP_SCRIPT, qm_id), shell=True,
                             stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
        result = "".join([x.decode("utf8") for x in p.stdout.readlines()])
        p.wait()
        write_log("%s:%s output: %s" % (self.address[0], self.address[1], result))

    def run(self):
        flag = False
        data = self.client.recv(BUF_SIZE)
        qm_id = 0
        if data:
            qm_id = int(bytes.decode(data, "utf-8"))
            if qm_id > 0:
                flag = True
        if flag:
            self.client.send('ok'.encode())
        else:
            self.client.send('error'.encode())
        self.client.close()
        if qm_id > 0:
            self.send_mail(qm_id)


class QuickMail(threading.Thread):
    def __init__(self, ip, port):
        threading.Thread.__init__(self)
        self.port = port
        self.ip = ip
        self.socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        self.socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        self.socket.bind((ip, port))
        self.socket.listen(10)

    def run(self):
        write_log("service startup on %s:%s" % (self.ip, self.port))
        while True:
            client, address = self.socket.accept()
            RunPHP(client, address).start()


lst = QuickMail(S_IP, S_PORT)
lst.start()