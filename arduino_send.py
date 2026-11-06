import sys
import serial
import time

puerto = 'COM5'
try:
    texto = sys.stdin.read().strip()
    if not texto:
        sys.exit(0)
    ser = serial.Serial(puerto, 9600, timeout=2)
    time.sleep(2)
    ser.write((texto + '\n').encode('utf-8', errors='ignore'))
    ser.flush()
    ser.close()
    print('OK')
except Exception as e:
    print('ERR')
    sys.exit(1)
