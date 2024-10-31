(
	function(){
		tinymce.create(
			"tinymce.plugins.ResalineShortcodes",
			{
				init: function(d,e) {},
				createControl:function(d,e)
				{
					var ed = tinymce.activeEditor;
					
					/*console.log("ED===>")
	
					console.log(ed.getLang('resaline.insert'))
					console.log(ed.getLang)
					console.log("YESYES")*/
					if(d=="resaline_shortcodes_button"){

						d=e.createMenuButton( "resaline_shortcodes_button",{
							title: ed.getLang('resaline.insert'),
							icons: false
							});

							
							var nb_calendars = parseInt(ed.getLang("resaline_datas.nb_calendars"));
							
							var a=this;d.onRenderMenu.add(function(c,b){
								for (var i=0; i< nb_calendars; i++){
									var cal = ed.getLang("resaline_datas.calendar_"+i).split(";");
									a.addImmediate(b, cal[0] + '-' + cal[1] ,'[resaline_calendar id="'+i+'"]' );
								}
								//a.addImmediate(b, ed.getLang('resaline.add_calendar'),'[resaline_calendar id=""]' );	
							});
						return d

					} // End IF Statement

					return null
				},

				addImmediate:function(d,e,a){d.add({title:e,onclick:function(){tinyMCE.activeEditor.execCommand( "mceInsertContent",false,a)}})}

			}
		);		

		tinymce.PluginManager.add( "ResalineShortcodes", tinymce.plugins.ResalineShortcodes);

	}
)();