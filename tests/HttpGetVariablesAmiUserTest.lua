local sent = {}
local output = {}

io.open = function()
    return { close = function() end }
end
io.lines = function(path)
    local value = path == "/var/etc/http_auth" and "test:test" or string.rep("a", 48)
    local returned = false
    return function()
        if returned then return nil end
        returned = true
        return value
    end
end

local responses = {
    "Response: Success\r\n\r\n",
    "Response: Success\r\nValue: test-linkedid\r\n\r\n",
    "Response: Goodbye\r\n\r\n",
}

local socket = {
    settimeout = function() end,
    connect = function() return true end,
    send = function(_, value)
        sent[#sent + 1] = value
        return #value
    end,
    receiveuntil = function()
        return function()
            local response = table.remove(responses, 1)
            return response
        end
    end,
    close = function() end,
}

ngx = {
    WARN = 1,
    HTTP_BAD_REQUEST = 400,
    HTTP_INTERNAL_SERVER_ERROR = 500,
    HTTP_FORBIDDEN = 403,
    HTTP_OK = 200,
    encode_base64 = function() return "dGVzdDp0ZXN0" end,
    decode_base64 = function(value) return value == "dGVzdDp0ZXN0" and "test:test" or nil end,
    var = { pt1c_authorization = "Basic   dGVzdDp0ZXN0" },
    log = function() end,
    say = function(value) output[#output + 1] = value end,
    exit = function(code) error({ exit_code = code }) end,
    req = {
        get_headers = function() return {} end,
        raw_header = function() return "GET / HTTP/1.1\r\n\r\n" end,
        get_uri_args = function()
            return { channel = "PJSIP/201-00000004", variables = "CHANNEL(linkedid)" }
        end,
    },
    shared = {
        asterisk_vars = {
            get = function() return nil end,
            set = function() end,
            flush_expired = function() end,
        },
    },
    socket = { tcp = function() return socket end },
}

local ok, result = pcall(dofile, "Lib/http_get_variables.lua")
if ok or type(result) ~= "table" or result.exit_code ~= 200 then
    io.stderr:write("Valid GetVar request did not complete successfully.\n")
    os.exit(1)
end

local packets = table.concat(sent, "")
if not string.find(packets, "Username: pt1ccore_getvar", 1, true)
    or not string.find(packets, "Secret: " .. string.rep("a", 48), 1, true)
    or string.find(packets, "phpagi", 1, true)
    or not string.find(packets, "Action: GetVar", 1, true) then
    io.stderr:write("Lua endpoint did not use the restricted AMI account correctly.\n")
    os.exit(1)
end

io.stdout:write("Lua restricted AMI user test passed.\n")
