Blender API — Quickstart (capturado desde docs.blender.org)

Fuente: https://docs.blender.org/api/current/info_quickstart.html

Contenido (extracto):

### Data Access

#### Accessing Data-Blocks

You can access Blender’s data with the Python API in the same way as the animation system or user interface; this implies that any setting that can be changed via a button can also be changed with Python. Accessing data from the currently loaded blend-file is done with the module `bpy.data`. It gives access to library data, for example:

>>> bpy.data.objects
>>> bpy.data.scenes
>>> bpy.data.materials

#### Accessing Collections

You will notice that an index as well as a string can be used to access members of the collection. Unlike Python dictionaries, both methods are available; however, the index of a member may change while running Blender.

#### Accessing Attributes

Once you have a data-block, such as a material, object, collection, etc., its attributes can be accessed much like you would change a setting using the graphical interface. In fact, the tooltip for each button also displays the Python attribute which can help in finding what settings to change in a script.

#### Data Creation/Removal

Data is added and removed via methods on the collections in `bpy.data`, e.g:

>>> mesh = bpy.data.meshes.new(name="MyMesh")
>>> bpy.data.meshes.remove(mesh)

#### Custom Properties

Python can access properties on any data-block that has an ID (data that can be linked in and accessed from `bpy.data`). When assigning a property, you can pick your own names, these will be created when needed or overwritten if they already exist. This data is saved with the blend-file and copied with objects.

(Esto es un extracto de la página; para ver la documentación completa consulta la fuente.)
