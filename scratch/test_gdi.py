import os
import sys
import tempfile

try:
    import win32print
    import win32ui
    import win32con
    HAS_WIN32 = True
except ImportError:
    HAS_WIN32 = False

try:
    from PIL import Image, ImageWin
    HAS_PIL = True
except ImportError:
    HAS_PIL = False

print(f"HAS_WIN32: {HAS_WIN32}, HAS_PIL: {HAS_PIL}")

def print_image_gdi(image_path, printer_name, copies=1):
    if not (HAS_WIN32 and HAS_PIL):
        print("Missing win32 or PIL")
        return False

    try:
        img = Image.open(image_path)
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

        doc_name = os.path.basename(image_path)

        for i in range(copies):
            print(f"Printing copy {i+1} of {copies} via GDI...")
            hDC.StartDoc(doc_name)
            hDC.StartPage()

            dib = ImageWin.Dib(img)
            dib.draw(hDC.GetHandle(), (x_offset, y_offset, x_offset + target_w, y_offset + target_h))

            hDC.EndPage()
            hDC.EndDoc()

        hDC.DeleteDC()
        print("GDI Print complete successfully!")
        return True
    except Exception as e:
        print(f"GDI error: {e}")
        return False

# Test with dummy image
if __name__ == "__main__":
    from PIL import ImageDraw
    img = Image.new('RGB', (800, 600), color=(73, 109, 137))
    d = ImageDraw.Draw(img)
    d.text((10,10), "Print Cafe GDI Test", fill=(255,255,0))
    tmp = tempfile.mktemp(suffix=".jpg")
    img.save(tmp)
    print(f"Test image created: {tmp}")
