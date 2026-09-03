local socket_connected = false

io.open = function()
    return { close = function() end }
end
io.lines = function()
    local returned = false
    return function()
        if returned then return nil end
        returned = true
        return "test:test"
    end
end

ngx = {
    WARN = 1,
    HTTP_BAD_REQUEST = 400,
    HTTP_FORBIDDEN = 403,
    HTTP_OK = 200,
    encode_base64 = function() return "dGVzdDp0ZXN0" end,
    decode_base64 = function(value) return value == "dGVzdDp0ZXN0" and "test:test" or nil end,
    var = { pt1c_authorization = "Basic   dGVzdDp0ZXN0" },
    log = function() end,
    say = function() end,
    exit = function(code) error({ exit_code = code }) end,
    req = {
        get_headers = function() return {} end,
        raw_header = function() return "GET / HTTP/1.1\r\n\r\n" end,
        get_uri_args = function()
            return {
                channel = "PJSIP/201-00000004\r\nAction: Command",
                variables = "CHANNEL(linkedid),EXTEN",
            }
        end,
    },
    shared = {
        asterisk_vars = {
            get = function() return nil end,
            set = function() end,
            flush_expired = function() end,
        },
    },
    socket = {
        tcp = function()
            socket_connected = true
            error("AMI socket must not be opened for invalid input")
        end,
    },
}

local ok, result = pcall(dofile, "Lib/http_get_variables.lua")
if ok or type(result) ~= "table" or result.exit_code ~= 400 then
    io.stderr:write("CR/LF injection was not rejected with HTTP 400.\n")
    os.exit(1)
end
if socket_connected then
    io.stderr:write("Invalid input reached the AMI socket.\n")
    os.exit(1)
end

io.stdout:write("Lua AMI injection test passed.\n")
