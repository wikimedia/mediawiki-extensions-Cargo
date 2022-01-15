local cargo = {}
local php

function cargo.setupInterface( options )
    -- Remove setup function
    cargo.setupInterface = nil

    -- Copy the PHP callbacks to a local variable, and remove the global
    php = mw_interface
    mw_interface = nil

    -- Do any other setup here

    -- Install into the mw global
    mw = mw or {}
    mw.ext = mw.ext or {}
    mw.ext.cargo = cargo

    -- Indicate that we're loaded
    package.loaded['mw.ext.cargo'] = cargo
end

function cargo.query(tables, fields, args)
    return php.query(tables, fields, args)
end

function cargo.formatTable(table, args)
	local result = php.format(table or {}, args or {})
	return result[0], result.noparse, result.isHtml
end

return cargo
