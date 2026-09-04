import os
import tempfile
import win32print
import win32ui
import win32con
from PIL import Image, ImageWin, ImageDraw

img = Image.new('RGB', (800, 600), color=(73, 109, 137))
d = ImageDraw.Draw(img)
d.text((10,10), "Print Cafe GDI Test", fill=(255,255,0))
tmp = tempfile.mktemp(suffix=".jpg")
img.save(tmp)

printer_name = "HP LaserJet 1020 (Copy 2)"
print("Testing GDI with printer:", printer_name)

try:
    img = Image.open(tmp)
    if img.mode != 'RGB':
        img = img.convert('RGB')

    hDC = win32ui.CreateDC()
    hDC.CreatePrinterDC(printer_name)

    printable_w = hDC.GetDeviceCaps(win32con.HORZRES)
    printable_h = hDC.GetDeviceCaps(win32con.VERTRES)

    img_w, img_h = img.size
    aspect = img_w / float(img_h)

    target_w = printable_w
    target_h = int(printable_w / aspect)
    if target_h > printable_h:
        target_h = printable_h
        target_w = int(printable_h * aspect)

    x_offset = int((printable_w - target_w) / 2)
    y_offset = int((printable_h - target_h) / 2)

    doc_name = os.path.basename(tmp)

    print(f"Printable size: {printable_w}x{printable_h}, target: {target_w}x{target_h}, offset: {x_offset},{y_offset}")

    hDC.StartDoc(doc_name)
    hDC.StartPage()

    dib = ImageWin.Dib(img)
    dib.draw(hDC.GetSafeHdc(), (x_offset, y_offset, x_offset + target_w, y_offset + target_h))

    hDC.EndPage()
    hDC.EndDoc()
    hDC.DeleteDC()
    print("SUCCESSFULLY SPOOLED VIA GDI TO PRINTER!")
except Exception as e:
    import traceback
    print("GDI PRINT ERROR:")
    traceback.print_exc()
