#!/usr/bin/python
from gi.repository import Gtk, GdkPixbuf, GObject
from PIL import Image
import sys
import json
import StringIO
import base64
import time
import threading
import imghdr

sys.path.insert(0, '../driver/python/')
from client import *

UPDATE_TIMEOUT = .1 # in seconds

_lock = threading.Lock()
def info(*args):
    with _lock:
        print("%s %s" % (threading.current_thread(), " ".join(map(str, args))))


class GTSDTest:
    def __init__(self, widget):
        self.widget = widget
        self.num_chunks = 8;
        
        if sys.argv[1]:
            servers = sys.argv[1].split(',')
            for (i, server) in enumerate(servers):
                hostPort = server.split(':')
                servers[i] = {'host': hostPort[0], 'port': int(hostPort[1])}
        else:
            servers = [{'host': 'localhost', 'port': 8124}]

        print servers

        self.client = Client(servers)

    def set_callback_chunk(self, callback):
        self.callback = callback

    def set_source_image(self, filename):
        self.source_image = filename

    def set_source_format(self, format):
        self.format = format;

    def resize(self):
        im = Image.open(self.source_image)
        chunk_size = im.size[0]/self.num_chunks, im.size[1]/self.num_chunks;
        chunks_data = [ [ 0 for i in range(self.num_chunks) ] for j in range(self.num_chunks) ]

        for i in range(self.num_chunks):
            for j in range(self.num_chunks):
                box = (chunk_size[0]*j, chunk_size[1]*i, chunk_size[0]*j+chunk_size[0], chunk_size[1]*i+chunk_size[1])
                chunk = im.crop(box)
                output = StringIO.StringIO()
                chunk.save(output, self.format.upper())
                resized = self.client.run("resize", json.dumps({"image": base64.b64encode(output.getvalue()),"width": chunk_size[0]/2, "height": chunk_size[1]/2}))
                self.callback(base64.b64decode(resized), i, j)
                GObject.idle_add(win.render)


class App(Gtk.Window):

    def __init__(self):
        Gtk.Window.__init__(self, title="Gerenciador de tarefas em sistemas distribuidos")

       
        
        hbox = Gtk.Box(spacing=6)
        selectFileButton = Gtk.Button("Choose File")
        selectFileButton.connect("clicked", self.on_file_clicked)
        hbox.pack_start(selectFileButton, True, True, 0)

        resizeButton = Gtk.Button("Resize")
        resizeButton.connect("clicked", self.resize_image)
        hbox.pack_start(resizeButton, True, True, 0)

        vbox = Gtk.Box(spacing=6, orientation=Gtk.Orientation.VERTICAL)
        
        vbox.pack_start(hbox, True, True, 0)
        self.layout = Gtk.Grid()
        vbox.pack_start(self.layout, True, True, 0)

        self.image = Gtk.Image()

        
        
        self.add(vbox)
        self.render()

    def data_to_pixbuf(self, data):
        l = GdkPixbuf.PixbufLoader.new_with_type(self.image_format)
        l.write(data)
        l.close()
        return l.get_pixbuf()

    def resize_image(self, widget):
        gtsd = GTSDTest(self)
        gtsd.set_source_image(self.filepath)
        gtsd.set_source_format(self.image_format)
        gtsd.set_callback_chunk(self.render_resized_chunk)
        self.image.destroy()
        t = threading.Thread(target=gtsd.resize)
        t.start()
        self.resize(self.image_size[0]/2, self.image_size[1]/2)


    def render_resized_chunk(self, chunk_data, i, j):
        chunk_image = Gtk.Image()
        chunk_image.set_from_pixbuf(self.data_to_pixbuf(chunk_data))
        self.layout.attach(chunk_image, j, i+1, 1, 1)

    def render(self):
        print "rendering"
        self.show_all()

    def on_file_clicked(self, widget):
        dialog = Gtk.FileChooserDialog("Please choose a file", self,
            Gtk.FileChooserAction.OPEN,
            (Gtk.STOCK_CANCEL, Gtk.ResponseType.CANCEL,
             Gtk.STOCK_OPEN, Gtk.ResponseType.OK))

        self.add_filters(dialog)

        response = dialog.run()
        if response == Gtk.ResponseType.OK:
            self.set_image_preview(dialog.get_filename());

        dialog.destroy()

    def set_image_preview(self, filepath):
        print self.get_size()
        self.filepath = filepath
        self.image.set_from_file(filepath)
        self.image_format = imghdr.what(filepath)
        im = Image.open(filepath)
        self.image_size = im.size;
        self.layout.attach(self.image, 0, 1, 8, 8)
        self.render()

    def add_filters(self, dialog):
        filter_text = Gtk.FileFilter()
        filter_text.set_name("Imagens")
        filter_text.add_mime_type("image/bmp")
        filter_text.add_mime_type("image/jpeg")
        filter_text.add_mime_type("image/png")
        dialog.add_filter(filter_text)

GObject.threads_init()

win = App()
win.connect("delete-event", Gtk.main_quit)

Gtk.main()
